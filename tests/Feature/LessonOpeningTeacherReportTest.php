<?php

use App\Exports\LessonOpeningTeacherReportExport;
use App\Models\User;
use App\Services\LessonOpeningTeacherReport;
use App\Services\TeacherMissedLessons;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

/**
 * Dars ochish hisoboti (registrator ofisi Excel'i): har bir o'qituvchi necha marta
 * baho qo'ymagan va shulardan nechtasi uchun ariza orqali tasdiq olib baho qo'ygan.
 * Qoida o'qituvchi popupi (TeacherMissedLessons) bilan bir xil bo'lishi kerak.
 *
 * Jadvallarda majburiy ustunlar ko'p: loRow() ularni jadvalning o'zidan bilib,
 * bo'sh qiymat bilan to'ldiradi — testda faqat kerakli maydonlar yoziladi.
 */
function loRow(string $table, array $attrs = []): int
{
    static $columns = [];
    $columns[$table] ??= Schema::getColumns($table);

    $row = [];
    foreach ($columns[$table] as $column) {
        if (($column['auto_increment'] ?? false) || $column['nullable'] || $column['default'] !== null) {
            continue;
        }
        $type = strtolower((string) $column['type_name']);
        $row[$column['name']] = match (true) {
            $type === 'enum' => preg_match("/enum\\('([^']*)'/", (string) $column['type'], $m) ? $m[1] : '',
            $type === 'json' => '[]',
            $type === 'date' => '2026-01-01',
            str_contains($type, 'time') => '2026-01-01 00:00:00',
            str_contains($type, 'int'), in_array($type, ['float', 'double', 'decimal', 'numeric', 'real', 'boolean', 'bool'], true) => 0,
            default => '',
        };
    }

    return DB::table($table)->insertGetId(array_merge($row, $attrs));
}

function loSeq(): int
{
    static $n = 100000;

    return ++$n;
}

function loTeacher(int $hemis, string $name, string $department = ''): void
{
    loRow('teachers', [
        'hemis_id' => $hemis, 'full_name' => $name, 'short_name' => $name, 'department' => $department,
        'employee_id_number' => (string) $hemis, 'is_active' => true,
    ]);
}

/** Guruh va uning bitta talabasini yaratadi; talabaning HEMIS id'sini qaytaradi. */
function loGroup(int $hemis, string $name, bool $withActiveStudent = true): int
{
    loRow('groups', ['group_hemis_id' => $hemis, 'name' => $name]);
    $seq = loSeq();

    // Faol talaba: student_status_code = 11 ("O'qimoqda"); boshqa kod — faol emas
    loRow('students', [
        'hemis_id' => $seq, 'student_id_number' => 'S'.$seq, 'full_name' => 'Talaba '.$seq,
        'group_id' => $hemis, 'student_status_code' => $withActiveStudent ? 11 : 12,
    ]);

    return $seq;   // talabaning HEMIS id'si — baholar shunga bog'lanadi
}

/** Jadvaldagi bitta juftlik: o'qituvchi, guruh, fan, sana. */
function loSlot(int $employee, string $employeeName, int $group, int $subject, string $subjectName, string $date, string $pair = '1', string $type = "Amaliy mashg'ulot", string $typeCode = '13'): void
{
    loRow('schedules', [
        'schedule_hemis_id' => loSeq(), 'subject_id' => $subject, 'subject_name' => $subjectName,
        'semester_code' => '11', 'education_year_current' => true, 'group_id' => $group,
        'employee_id' => $employee, 'employee_name' => $employeeName,
        'training_type_code' => $typeCode, 'training_type_name' => $type,
        'lesson_pair_code' => $pair, 'lesson_date' => $date.' 00:00:00',
    ]);
}

/** Juftlikka qo'yilgan baho; $studentHemis — loGroup() qaytargan talaba. */
function loGrade(int $studentHemis, int $subject, string $date, string $pair = '1'): void
{
    loRow('student_grades', [
        'hemis_id' => loSeq(), 'student_id' => DB::table('students')->where('hemis_id', $studentHemis)->value('id'),
        'student_hemis_id' => $studentHemis,
        'subject_id' => $subject, 'semester_code' => '11', 'training_type_code' => '13',
        'lesson_pair_code' => $pair, 'lesson_date' => $date.' 09:00:00',
        'grade' => 80, 'status' => 'recorded', 'reason' => null,
    ]);
}

function loOpening(string $status, int $group, int $subject, string $date, ?int $teacherId, ?string $teacherName): void
{
    DB::table('lesson_openings')->insert([
        'group_hemis_id' => $group, 'subject_id' => $subject, 'semester_code' => '11',
        'lesson_date' => $date, 'opened_by_id' => 1, 'opened_by_name' => 'Ariza yuboruvchi',
        'opened_by_guard' => 'teacher', 'status' => $status,
        // Faol ariza muddati kelajakda, tugagani o'tmishda, kutilayotgan/rad etilganda yo'q
        'deadline' => match ($status) {
            'active' => now('Asia/Tashkent')->addDays(2),
            'expired' => now('Asia/Tashkent')->subDay(),
            default => null,
        },
        'teacher_id' => $teacherId, 'teacher_name' => $teacherName, 'request_number' => $teacherId ? 1 : null,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/**
 * Bugun 2026-09-29, semestr 1-sentabrdan. Kunlar (1-sentabrdan keyingi):
 *
 *  Aliyev (1001), G501 / Farmakologiya:
 *    02 — vaqtida baho qo'ygan                       -> hisobga kirmaydi
 *    03 — baho yo'q, ariza yo'q                       -> ariza yubormagan
 *    04 — ariza tasdiqlangan, baho hozir bor          -> ariza orqali baho qo'ygan
 *    07 — ariza tasdiqlangan (muddati tugagan), baho yo'q -> tasdiqlangan, baho yo'q
 *    08 — ariza kutilmoqda
 *    09 — ariza rad etilgan
 *    10 — ikki juftlik: 1-sida baho bor, 2-sida yo'q  -> ariza yubormagan (juftlik qoidasi)
 *    11 — ma'ruza, baho yo'q                          -> hisobga kirmaydi
 *    29 — bugun, baho yo'q                            -> hisobga kirmaydi
 *    avgust — davrdan oldin                           -> hisobga kirmaydi
 *  Karimova (1002), G502 / Patologik anatomiya: 03 — ariza yubormagan; 04 — ariza orqali baho qo'ygan
 *  Yo'ldoshev (1003), G501 / Transfuziologiya: 15 — teacher_id yozilmagan eski ariza, tasdiqlangan, baho bor
 *  Sobirov (1004): hamma kunga baho qo'ygan          -> ro'yxatda yo'q
 *  Jadvalda bor, o'qituvchilar jadvalida yo'q (2001): 18 — ariza yubormagan
 *  G503 (faol talabasi yo'q): 17 — baho yo'q         -> hisobga kirmaydi
 */
function loWorld(): array
{
    loTeacher(1001, 'Aliyev Vali', 'Farmakologiya kafedrasi');
    loTeacher(1002, 'Karimova Nodira', 'Patologik anatomiya kafedrasi');
    loTeacher(1003, "Yo'ldoshev Sardor");
    loTeacher(1004, 'Sobirov Anvar');
    $ids = [
        'aliyev' => (int) DB::table('teachers')->where('hemis_id', 1001)->value('id'),
        'karimova' => (int) DB::table('teachers')->where('hemis_id', 1002)->value('id'),
    ];

    $s501 = loGroup(501, 'D1-01');
    $s502 = loGroup(502, 'D1-02');
    loGroup(503, 'D1-03', false);

    $farm = [9001, 'Farmakologiya'];
    $path = [9002, 'Patologik anatomiya'];
    $trans = [9003, 'Transfuziologiya'];

    // Aliyev
    foreach (['2026-09-02', '2026-09-03', '2026-09-04', '2026-09-07', '2026-09-08', '2026-09-09', '2026-09-29', '2026-08-25'] as $day) {
        loSlot(1001, 'Aliyev Vali', 501, $farm[0], $farm[1], $day);
    }
    loSlot(1001, 'Aliyev Vali', 501, $farm[0], $farm[1], '2026-09-10', '1');
    loSlot(1001, 'Aliyev Vali', 501, $farm[0], $farm[1], '2026-09-10', '2');
    loSlot(1001, 'Aliyev Vali', 501, $farm[0], $farm[1], '2026-09-11', '1', "Ma'ruza", '11');
    foreach (['2026-09-02', '2026-09-04'] as $day) {
        loGrade($s501, $farm[0], $day);
    }
    loGrade($s501, $farm[0], '2026-09-10', '1');
    loOpening('active', 501, $farm[0], '2026-09-04', $ids['aliyev'], 'Aliyev Vali');
    loOpening('expired', 501, $farm[0], '2026-09-07', $ids['aliyev'], 'Aliyev Vali');
    loOpening('pending', 501, $farm[0], '2026-09-08', $ids['aliyev'], 'Aliyev Vali');
    loOpening('rejected', 501, $farm[0], '2026-09-09', $ids['aliyev'], 'Aliyev Vali');
    loOpening('active', 501, $farm[0], '2026-08-20', $ids['aliyev'], 'Aliyev Vali');   // davrdan oldin

    // Karimova
    loSlot(1002, 'Karimova Nodira', 502, $path[0], $path[1], '2026-09-03');
    loSlot(1002, 'Karimova Nodira', 502, $path[0], $path[1], '2026-09-04');
    loGrade($s502, $path[0], '2026-09-04');
    loOpening('active', 502, $path[0], '2026-09-04', $ids['karimova'], 'Karimova Nodira');

    // Yo'ldoshev: teacher_id yozilmagan eski ariza — kun jadvaldagi o'qituvchiga yoziladi
    loSlot(1003, "Yo'ldoshev Sardor", 501, $trans[0], $trans[1], '2026-09-15');
    loGrade($s501, $trans[0], '2026-09-15');
    loOpening('active', 501, $trans[0], '2026-09-15', null, null);

    // Sobirov: hamma kunga baho qo'ygan
    loSlot(1004, 'Sobirov Anvar', 502, $path[0], $path[1], '2026-09-16');
    loGrade($s502, $path[0], '2026-09-16');

    // Faol talabasi yo'q guruh
    loSlot(1002, 'Karimova Nodira', 503, $path[0], $path[1], '2026-09-17');

    // O'qituvchilar jadvalida yo'q xodim — ismi jadvaldan olinadi
    loSlot(2001, 'Fantom Ustoz', 502, $path[0], $path[1], '2026-09-18');

    return $ids;
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Tashkent'));
    loWorld();
});

afterEach(function () {
    Carbon::setTestNow();
});

function loTeacherRow(array $report, string $name): array
{
    $row = collect($report['teachers'])->firstWhere('name', $name);
    expect($row)->not->toBeNull("{$name} hisobotda yo'q");

    return $row;
}

test('har bir kun bitta holatga tushadi va jami ularning yig\'indisi', function () {
    $report = app(LessonOpeningTeacherReport::class)->build();

    $aliyev = loTeacherRow($report, 'Aliyev Vali');
    expect($aliyev)->toMatchArray([
        'department' => 'Farmakologiya kafedrasi',
        'total' => 6,
        'graded' => 1,       // 04
        'approved' => 1,     // 07
        'pending' => 1,      // 08
        'rejected' => 1,     // 09
        'no_request' => 2,   // 03 va ikki juftlikli 10
    ]);

    expect(loTeacherRow($report, 'Karimova Nodira'))->toMatchArray(['total' => 2, 'graded' => 1, 'no_request' => 1]);
    // teacher_id yozilmagan ariza — jadvaldagi o'qituvchiga
    expect(loTeacherRow($report, "Yo'ldoshev Sardor"))->toMatchArray(['total' => 1, 'graded' => 1]);
    // O'qituvchilar jadvalida yo'q xodim — jadvaldagi ismi bilan
    expect(loTeacherRow($report, 'Fantom Ustoz'))->toMatchArray(['total' => 1, 'no_request' => 1]);

    foreach ($report['teachers'] as $row) {
        expect($row['total'])->toBe($row['graded'] + $row['approved'] + $row['pending'] + $row['rejected'] + $row['no_request']);
    }
});

test('baho qo\'yilmagan holati yo\'q o\'qituvchi va hisobga kirmaydigan kunlar ro\'yxatda yo\'q', function () {
    $report = app(LessonOpeningTeacherReport::class)->build();

    expect(collect($report['teachers'])->pluck('name')->all())
        ->toBe(['Aliyev Vali', 'Karimova Nodira', 'Fantom Ustoz', "Yo'ldoshev Sardor"]);   // ko'p qoldirgan birinchi, teng bo'lsa ism bo'yicha

    $aliyevDates = collect($report['days'])->where('teacher', 'Aliyev Vali')->pluck('date')->all();
    // Ma'ruza (11), bugun (29), davrdan oldingi (avgust) va vaqtida baholangan (02) yo'q
    expect($aliyevDates)->toBe(['2026-09-03', '2026-09-04', '2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10']);

    // Faol talabasi yo'q guruh (17-sana) Karimovaga tushmaydi
    expect(collect($report['days'])->where('teacher', 'Karimova Nodira')->pluck('date')->all())
        ->toBe(['2026-09-03', '2026-09-04']);
});

test('umumiy qator o\'qituvchilar yig\'indisiga teng va davr kechagacha', function () {
    $report = app(LessonOpeningTeacherReport::class)->build();

    expect($report['totals'])->toBe([
        'graded' => 3, 'approved' => 1, 'pending' => 1, 'rejected' => 1, 'no_request' => 4, 'total' => 10,
    ]);
    expect(count($report['days']))->toBe(10);
    expect($report['from']->toDateString())->toBe('2026-09-01');
    expect($report['to']->toDateString())->toBe('2026-09-28');
});

test('o\'qituvchi popupi o\'sha kunlarni ariza yuborilmaganlari va rad etilganlari bilan ko\'rsatadi', function () {
    $teacher = App\Models\Teacher::where('hemis_id', 1001)->first();

    $days = app(TeacherMissedLessons::class)->forTeacher($teacher);

    // Tasdiqlangan (04, 07) va kutilayotgan (08) arizali kunlar chiqarilmaydi; rad etilgani qoladi
    expect($days->pluck('lesson_date')->all())->toBe(['2026-09-10', '2026-09-09', '2026-09-03']);
    expect($days->pluck('rejected')->all())->toBe([false, true, false]);
});

test('Excel ikki varaqli: yig\'ma va kunlar, sonlar hisobotdagiga teng', function () {
    $report = app(LessonOpeningTeacherReport::class)->build();

    $file = tempnam(sys_get_temp_dir(), 'lo-report').'.xlsx';
    file_put_contents($file, Excel::raw(new LessonOpeningTeacherReportExport($report), ExcelWriter::XLSX));
    $book = IOFactory::load($file);
    @unlink($file);

    expect($book->getSheetNames())->toBe(["O'qituvchilar", 'Kunlar']);

    // 4-qator — sarlavha, 5 dan — o'qituvchilar
    $summary = $book->getSheet(0);
    expect($summary->getCell('A1')->getValue())->toContain("o'qituvchilar kesimida");
    expect($summary->getCell('A2')->getValue())->toContain('01.09.2026 — 28.09.2026');
    expect($summary->getCell('D4')->getValue())->toBe("Baho qo'yilmagan holatlar (jami)");
    expect($summary->getCell('E4')->getValue())->toBe("Ariza orqali tasdiq olib, baho qo'ygan");

    expect($summary->getCell('B5')->getValue())->toBe('Aliyev Vali');
    expect($summary->getCell('C5')->getValue())->toBe('Farmakologiya kafedrasi');
    expect((int) $summary->getCell('D5')->getValue())->toBe(6);
    expect((int) $summary->getCell('E5')->getValue())->toBe(1);
    expect((int) $summary->getCell('F5')->getValue())->toBe(1);
    expect((int) $summary->getCell('G5')->getValue())->toBe(1);
    expect((int) $summary->getCell('H5')->getValue())->toBe(1);
    expect((int) $summary->getCell('I5')->getValue())->toBe(2);

    // Oxirgi qator — "Jami"
    $last = 4 + count($report['teachers']) + 1;
    expect($summary->getCell("B{$last}")->getValue())->toBe('Jami');
    expect((int) $summary->getCell("D{$last}")->getValue())->toBe(10);
    expect((int) $summary->getCell("E{$last}")->getValue())->toBe(3);

    $days = $book->getSheet(1);
    expect($days->getHighestRow())->toBe(11);   // sarlavha + 10 kun
    expect($days->getCell('B2')->getValue())->toBe('Aliyev Vali');
    expect($days->getCell('C2')->getValue())->toBe('D1-01');
    expect($days->getCell('D2')->getValue())->toBe('Farmakologiya');
    expect($days->getCell('E2')->getValue())->toBe('03.09.2026');
    expect($days->getCell('F2')->getValue())->toBe('Ariza yubormagan');
});

test('bo\'sh davrda ham Excel xatosiz tuziladi', function () {
    DB::table('schedules')->delete();
    DB::table('lesson_openings')->delete();

    $report = app(LessonOpeningTeacherReport::class)->build();
    expect($report['teachers'])->toBe([]);
    expect($report['totals']['total'])->toBe(0);

    $file = tempnam(sys_get_temp_dir(), 'lo-report').'.xlsx';
    file_put_contents($file, Excel::raw(new LessonOpeningTeacherReportExport($report), ExcelWriter::XLSX));
    $book = IOFactory::load($file);
    @unlink($file);

    expect($book->getSheet(0)->getCell('A5')->getValue())->toContain("baho qo'yilmagan holat topilmadi");
});

function loUser(string $role, string $name): User
{
    Role::findOrCreate($role, 'web');
    $id = loRow('users', ['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.test', 'password' => bcrypt('secret')]);
    $user = User::findOrFail($id);
    $user->assignRole($role);

    return $user;
}

/** Tasdiqlovchi o'qituvchi — ishlab chiqarishda registrator/prorektor rollari xodimlarda turadi. */
function loApprover(string $role, string $name): void
{
    Role::findOrCreate($role, 'web');
    loTeacher(loSeq(), $name);
    App\Models\Teacher::where('full_name', $name)->firstOrFail()->assignRole($role);
}

test('registrator ofisi Excel\'ni yuklab oladi, rolsiz foydalanuvchi olmaydi', function () {
    $registrar = loUser('registrator_ofisi', 'Registrator Bir');

    $response = $this->actingAs($registrar, 'web')->get(route('admin.lesson-opening-requests.export'));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))
        ->toContain('dars-ochish-oqituvchilar-2026-09-29-1000.xlsx');

    // Ariza sahifasiga kira olmaydigan rol — RoleMiddleware boshqa sahifaga yo'naltiradi
    $teacher = loUser('oqituvchi', 'Oddiy Ustoz');
    $this->actingAs($teacher, 'web')->get(route('admin.lesson-opening-requests.export'))->assertRedirect();
});

test('sahifada faqat tasdiqlovchi tanlovlari va Excel tugmasi qoladi', function () {
    // Ikkitadan xodim bo'lsa tanlov chiqadi (ishlab chiqarishdagidek — xodimlar)
    loApprover('registrator_ofisi', 'Registrator Bir');
    loApprover('registrator_ofisi', 'Registrator Ikki');
    loApprover('oquv_prorektori', 'Prorektor Bir');
    loApprover('oquv_prorektori', 'Prorektor Ikki');
    $admin = loUser('superadmin', 'Bosh Admin');

    // Vite manifesti test muhitida qurilmaydi — sahifa mazmuni tekshiriladi, aktivlar emas
    $html = $this->withoutVite()->actingAs($admin, 'web')->get(route('admin.lesson-opening-requests.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Registratordan:')
        ->toContain('Prorektor:')
        ->toContain(route('admin.lesson-opening-requests.export'))
        // Olib tashlangan elementlar
        ->not->toContain('Tasdiqlangach baho qo&#039;yish muddati')
        ->not->toContain("Tasdiqlangach baho qo'yish muddati")
        ->not->toContain('Test rejimi')
        ->not->toContain("eskilarini ko'rsatish");
});
