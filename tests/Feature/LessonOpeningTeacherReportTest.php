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
function loGroup(int $hemis, string $name, bool $withActiveStudent = true, string $educationType = 'Bakalavr', ?string $educationCode = null): int
{
    loRow('groups', ['group_hemis_id' => $hemis, 'name' => $name]);
    $seq = loSeq();

    // Faol talaba: student_status_code = 11 ("O'qimoqda"); boshqa kod — faol emas.
    // LMS bakalavrni education_type_code = '11' yoki nom (bakalavr) bo'yicha ajratadi.
    $code = $educationCode ?? ($educationType === 'Bakalavr' ? '11' : '12');
    loRow('students', [
        'hemis_id' => $seq, 'student_id_number' => 'S'.$seq, 'full_name' => 'Talaba '.$seq,
        'group_id' => $hemis, 'student_status_code' => $withActiveStudent ? 11 : 12,
        'education_type_name' => $educationType, 'education_type_code' => $code,
    ]);

    return $seq;   // talabaning HEMIS id'si — baholar shunga bog'lanadi
}

/** Mavjud guruhga qo'shimcha faol bakalavr talaba qo'shadi; HEMIS id qaytaradi. */
function loExtraStudent(int $group): int
{
    $seq = loSeq();
    loRow('students', [
        'hemis_id' => $seq, 'student_id_number' => 'S'.$seq, 'full_name' => 'Talaba '.$seq,
        'group_id' => $group, 'student_status_code' => 11,
        'education_type_name' => 'Bakalavr', 'education_type_code' => '11',
    ]);

    return $seq;
}

/** Jadvaldagi bitta juftlik: o'qituvchi, guruh, fan, sana, juftlik (kod, nomi, vaqti). */
function loSlot(int $employee, string $employeeName, int $group, int $subject, string $subjectName, string $date, string $pair = '1', string $type = "Amaliy mashg'ulot", string $typeCode = '13'): void
{
    // Juftlik vaqtlari tibbiyot universiteti standarti
    $times = [
        '1' => ['1-juftlik', '08:30:00', '09:50:00'],
        '2' => ['2-juftlik', '10:00:00', '11:20:00'],
        '3' => ['3-juftlik', '12:00:00', '13:20:00'],
    ];
    [$pairName, $start, $end] = $times[$pair] ?? [$pair.'-juftlik', '00:00:00', '00:00:00'];

    loRow('schedules', [
        'schedule_hemis_id' => loSeq(), 'subject_id' => $subject, 'subject_name' => $subjectName,
        'semester_code' => '11', 'semester_name' => '3-semestr', 'education_year_current' => true, 'group_id' => $group,
        'employee_id' => $employee, 'employee_name' => $employeeName,
        'training_type_code' => $typeCode, 'training_type_name' => $type,
        'lesson_pair_code' => $pair, 'lesson_pair_name' => $pairName,
        'lesson_pair_start_time' => $start, 'lesson_pair_end_time' => $end,
        'lesson_date' => $date.' 00:00:00',
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

/** Juftlikka NB (davomat): grade=null, reason='absent'. */
function loNb(int $studentHemis, int $subject, string $date, string $pair = '1'): void
{
    loRow('student_grades', [
        'hemis_id' => loSeq(), 'student_id' => DB::table('students')->where('hemis_id', $studentHemis)->value('id'),
        'student_hemis_id' => $studentHemis,
        'subject_id' => $subject, 'semester_code' => '11', 'training_type_code' => '13',
        'lesson_pair_code' => $pair, 'lesson_date' => $date.' 09:00:00',
        'grade' => null, 'status' => 'recorded', 'reason' => 'absent',
    ]);
}

function loOpening(string $status, int $group, int $subject, string $date, ?int $teacherId, ?string $teacherName, string $applicant = 'Ariza Yuboruvchi', ?string $approver = 'Registrator Boshliq'): void
{
    // Ochilgan (completed/active/expired) arizalarni registrator tasdiqlagan bo'ladi
    $opened = in_array($status, ['completed', 'active', 'expired'], true);
    DB::table('lesson_openings')->insert([
        'group_hemis_id' => $group, 'subject_id' => $subject, 'semester_code' => '11',
        'lesson_date' => $date, 'opened_by_id' => 1, 'opened_by_name' => $applicant,
        'opened_by_guard' => 'teacher', 'status' => $status,
        // Faol ariza muddati kelajakda, tugagani o'tmishda, kutilayotgan/rad etilganda yo'q
        'deadline' => match ($status) {
            'active' => now('Asia/Tashkent')->addDays(2),
            'expired', 'completed' => now('Asia/Tashkent')->subDay(),
            default => null,
        },
        'registrar_status' => $opened ? 'approved' : ($status === 'rejected' ? 'rejected' : null),
        'registrar_name' => $opened ? $approver : null,
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

    // Magistratura guruhi — bakalavr emas, hisobotга umuman kirmasligi kerak
    loTeacher(1005, 'Magistr Ustoz', 'Magistratura kafedrasi');
    loGroup(504, 'M1-01', true, 'Magistr');
    loSlot(1005, 'Magistr Ustoz', 504, 9004, 'Magistr fani', '2026-09-05');   // baho yo'q, ariza yo'q

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

    // ungraded_students — baho qo'yilmagan talabalar yig'indisi (ustun sifatida
    // Excelda chiqadi); bu yerda holatlar sonini tekshiramiz.
    expect(collect($report['totals'])->except('ungraded_students')->all())->toBe([
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
    expect($days->getHighestRow())->toBe(11);   // sarlavha + 10 kun (magistr guruhi kirmaydi)
    // Ustunlar: №, O'qituvchi, Kafedra, Guruh, Fan, Sana, Juftlik, Soat, Holat, So'rov, Ariza sanasi
    expect($days->getCell('A1')->getValue())->toBe('№');
    expect($days->getCell('C1')->getValue())->toBe('Kafedra');
    expect($days->getCell('E1')->getValue())->toBe('Kurs');
    expect($days->getCell('F1')->getValue())->toBe('Semestr');
    expect($days->getCell('I1')->getValue())->toBe('Juftlik');
    expect($days->getCell('J1')->getValue())->toBe('Soat');
    // 1-qator: Aliyev (alifbo bo'yicha birinchi), 03-sentabr, 1-juftlik
    expect($days->getCell('B2')->getValue())->toBe('Aliyev Vali');
    expect($days->getCell('C2')->getValue())->toBe('Farmakologiya kafedrasi');   // kafedra name dan keyin
    expect($days->getCell('D2')->getValue())->toBe('D1-01');
    expect($days->getCell('E2')->getValue())->toBe('2-kurs');
    expect($days->getCell('F2')->getValue())->toBe('3-semestr');
    expect($days->getCell('G2')->getValue())->toBe('Farmakologiya');
    expect($days->getCell('H2')->getValue())->toBe('03.09.2026');
    expect($days->getCell('I2')->getValue())->toBe('1-juftlik');
    expect($days->getCell('J2')->getValue())->toBe('08:30-09:50');
    expect($days->getCell('K2')->getValue())->toBe('Ariza yubormagan');
    // 10-sentabr (Aliyevning 6-qatori, r7): 1-juftlikka baho qo'yilgan, 2-juftlik ochilmagan
    expect($days->getCell('H7')->getValue())->toBe('10.09.2026');
    expect($days->getCell('I7')->getValue())->toBe('2-juftlik');
    expect($days->getCell('J7')->getValue())->toBe('10:00-11:20');
    // Yangi ustunlar: So'rov yuborgan (L) va Tasdiqlaganlar (M)
    expect($days->getCell('N1')->getValue())->toBe("So'rov yuborgan");
    expect($days->getCell('O1')->getValue())->toBe('Tasdiqlaganlar');
    // 2-qator (Aliyev, 03-sentabr) — ariza yubormagan, ustunlar bo'sh
    expect($days->getCell('N2')->getValue())->toBeIn(['', null]);
    expect($days->getCell('O2')->getValue())->toBeIn(['', null]);
    // 3-qator (Aliyev, 04-sentabr) — ochilgan ariza: yuboruvchi va tasdiqlagan
    expect($days->getCell('H3')->getValue())->toBe('04.09.2026');
    expect($days->getCell('N3')->getValue())->toBe('Ariza Yuboruvchi');
    expect($days->getCell('O3')->getValue())->toBe('Registrator Boshliq');

    // Magistr o'qituvchi umuman yo'q
    $allNames = [];
    for ($r = 2; $r <= 11; $r++) {
        $allNames[] = $days->getCell('B'.$r)->getValue();
    }
    expect($allNames)->not->toContain('Magistr Ustoz');
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

test('fon eksporti: boshlash -> holat -> yuklab olish (sana oralig\'i bilan)', function () {
    // Test muhitida navbat sync — job POST ichida darhol ishlaydi
    $registrar = loUser('registrator_ofisi', 'Registrator Bir');

    $start = $this->actingAs($registrar, 'web')->postJson(route('admin.lesson-opening-requests.export.start'), [
        'date_from' => '2026-09-03',
        'date_to' => '2026-09-10',
    ]);
    $start->assertOk();
    $key = $start->json('export_key');
    expect($key)->not->toBeNull();

    // Sync navbat tugagach holat "done"
    $status = $this->actingAs($registrar, 'web')->getJson(route('admin.lesson-opening-requests.export.status', ['export_key' => $key]));
    $status->assertOk()->assertJson(['status' => 'done']);
    expect($status->json('file_name'))->toBe('dars-ochish-oqituvchilar-2026-09-03_2026-09-10.xlsx');

    // Fayl yuklab olinadi
    $download = $this->actingAs($registrar, 'web')->get(route('admin.lesson-opening-requests.export.download', ['export_key' => $key]));
    $download->assertOk();
    expect($download->headers->get('content-disposition'))->toContain('dars-ochish-oqituvchilar-2026-09-03_2026-09-10.xlsx');

    // Ariza sahifasiga kira olmaydigan rol — RoleMiddleware boshqa sahifaga yo'naltiradi
    $teacher = loUser('oqituvchi', 'Oddiy Ustoz');
    $this->actingAs($teacher, 'web')->post(route('admin.lesson-opening-requests.export.start'))->assertRedirect();
});

test('sana oralig\'i faqat shu oraliqdagi kunlarni hisoblaydi', function () {
    $report = app(LessonOpeningTeacherReport::class);

    // Aliyev kunlari: 03,04,07,08,09,10 (semestr bo'yicha). Faqat 07-09 oralig'i:
    $range = $report->build('2026-09-07', '2026-09-09');
    $aliyev = collect($range['teachers'])->firstWhere('name', 'Aliyev Vali');

    expect($range['from']->toDateString())->toBe('2026-09-07');
    expect($range['to']->toDateString())->toBe('2026-09-09');
    expect(collect($range['days'])->where('teacher', 'Aliyev Vali')->pluck('date')->all())
        ->toBe(['2026-09-07', '2026-09-08', '2026-09-09']);
    expect($aliyev)->toMatchArray(['total' => 3, 'approved' => 1, 'pending' => 1, 'rejected' => 1]);

    // Kelajakdagi yuqori chegara kechagacha qisqaradi (bugun 2026-09-29)
    $capped = $report->build('2026-09-25', '2026-12-31');
    expect($capped['to']->toDateString())->toBe('2026-09-28');
});

test('faqat bakalavr: magistr va ordinatura guruhlari chiqmaydi, bakalavr (kod 11) chiqadi', function () {
    // Ordinatura guruhi (kod 12, nomi Ordinatura) — chiqmasligi kerak
    loTeacher(1006, 'Ordinator Ustoz', 'Ordinatura kafedrasi');
    loGroup(505, 'TRAVMA-2027', true, 'Ordinatura');
    loSlot(1006, 'Ordinator Ustoz', 505, 9005, 'Travmatologiya', '2026-09-05');

    // Bakalavr guruhi, lekin nomi bo'sh — faqat education_type_code = '11' bo'yicha aniqlanadi
    loTeacher(1007, 'Kodli Ustoz', 'Anatomiya kafedrasi');
    loGroup(506, 'B1-99', true, '', '11');
    loSlot(1007, 'Kodli Ustoz', 506, 9006, 'Anatomiya', '2026-09-05');

    $report = app(LessonOpeningTeacherReport::class)->build();
    $names = collect($report['teachers'])->pluck('name')->all();

    // Magistr (loWorld dan) va ordinatura chiqmaydi
    expect($names)->not->toContain('Magistr Ustoz');
    expect($names)->not->toContain('Ordinator Ustoz');
    expect(collect($report['days'])->pluck('teacher')->all())->not->toContain('Ordinator Ustoz');

    // Kod bo'yicha bakalavr (nomi bo'sh) — chiqadi
    expect($names)->toContain('Kodli Ustoz');
});

test('baho qo\'yilmaganlik birligi: guruh + fan + kun (juftlik emas)', function () {
    // Bitta o'qituvchi, bitta bakalavr guruh:
    //   - X fan, 22-kun: ikkala juftlik ochilmagan  -> 1 holat
    //   - X fan, 23-kun: bitta juftlik ochilmagan     -> alohida (boshqa kun) 1 holat
    //   - Y fan, 22-kun: ochilmagan                    -> alohida (boshqa fan) 1 holat
    loTeacher(1008, 'Birlik Ustoz', 'Test kafedrasi');
    loGroup(507, 'B1-77');
    loSlot(1008, 'Birlik Ustoz', 507, 9101, 'X fan', '2026-09-22', '1');
    loSlot(1008, 'Birlik Ustoz', 507, 9101, 'X fan', '2026-09-22', '2');   // o'sha kun, o'sha fan, 2-juftlik
    loSlot(1008, 'Birlik Ustoz', 507, 9101, 'X fan', '2026-09-23', '1');   // boshqa kun
    loSlot(1008, 'Birlik Ustoz', 507, 9102, 'Y fan', '2026-09-22', '1');   // o'sha kun, boshqa fan

    $report = app(LessonOpeningTeacherReport::class)->build();
    $ustoz = collect($report['teachers'])->firstWhere('name', 'Birlik Ustoz');

    // 3 ta alohida holat: (X,22), (X,23), (Y,22). 22-kundagi 2 juftlik bitta holat.
    expect($ustoz)->toMatchArray(['total' => 3, 'no_request' => 3]);

    // Kunlar varag'ida har holat bitta qator (jami 3); 22-kun X fan ikkala
    // juftlik o'sha qatorда birga ko'rsatiladi.
    $rows = collect($report['days'])->where('teacher', 'Birlik Ustoz')->values();
    expect($rows->count())->toBe(3);
    $x22 = $rows->where('subject', 'X fan')->where('date', '2026-09-22')->first();
    expect($x22['pair'])->toBe('1-juftlik, 2-juftlik');
});

test('completed holati baho qo\'yilgan deb sanaladi, PENDINGga tushmaydi', function () {
    // SendLessonOpeningReminders baho to'liq qo'yilgach status = 'completed' qiladi
    // (active/expired emas). Bu "Ariza orqali tasdiq olib, baho qo'ygan" deb sanalishi kerak.
    loTeacher(1009, 'Complete Ustoz', 'Test kafedrasi');
    loGroup(508, 'B1-88');
    loSlot(1009, 'Complete Ustoz', 508, 9201, 'Z fan', '2026-09-20');
    $tid = (int) DB::table('teachers')->where('hemis_id', 1009)->value('id');
    loOpening('completed', 508, 9201, '2026-09-20', $tid, 'Complete Ustoz');

    $report = app(LessonOpeningTeacherReport::class)->build();
    $ustoz = collect($report['teachers'])->firstWhere('name', 'Complete Ustoz');

    // completed -> baho qo'yilgan (graded), pending emas
    expect($ustoz)->toMatchArray(['total' => 1, 'graded' => 1, 'approved' => 0, 'pending' => 0]);

    // Kunlar varag'ida bu qator "Ariza orqali tasdiq olib, baho qo'ygan" va juftlik bo'sh
    $row = collect($report['days'])->firstWhere('teacher', 'Complete Ustoz');
    expect($row['status'])->toBe('graded');
    expect($row['pair'])->toBe('');
});

test('bir necha tasdiqlovchi bitta katakda vergul bilan chiqadi', function () {
    // 3-so'rov: registrator + o'quv bo'limi + prorektor tasdiqlaydi
    loTeacher(1010, 'Uch Bosqich Ustoz', 'Test kafedrasi');
    loGroup(509, 'B1-33');
    loSlot(1010, 'Uch Bosqich Ustoz', 509, 9301, 'W fan', '2026-09-20');
    $tid = (int) DB::table('teachers')->where('hemis_id', 1010)->value('id');
    DB::table('lesson_openings')->insert([
        'group_hemis_id' => 509, 'subject_id' => 9301, 'semester_code' => '11',
        'lesson_date' => '2026-09-20', 'opened_by_id' => 1, 'opened_by_name' => 'Domla Yuboruvchi',
        'opened_by_guard' => 'teacher', 'status' => 'completed', 'deadline' => now()->subDay(),
        'registrar_status' => 'approved', 'registrar_name' => 'Registrator F.I.Sh',
        'department_status' => 'approved', 'department_name' => "O'quv Bo'limi F.I.Sh",
        'prorektor_status' => 'approved',
        'prorektor_approvals' => json_encode(['teacher:5' => ['name' => 'Prorektor F.I.Sh', 'decision' => 'approved', 'at' => now()->toDateTimeString()]]),
        'teacher_id' => $tid, 'teacher_name' => 'Uch Bosqich Ustoz', 'request_number' => 3,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $report = app(LessonOpeningTeacherReport::class)->build();
    $row = collect($report['days'])->firstWhere('teacher', 'Uch Bosqich Ustoz');

    expect($row['course'])->toBe('2-kurs');
    expect($row['semester'])->toBe('3-semestr');
    expect($row['applicant'])->toBe('Domla Yuboruvchi');
    // Uch bosqich bitta katakda, vergul bilan
    expect($row['approvers'])->toBe("Registrator F.I.Sh, O'quv Bo'limi F.I.Sh, Prorektor F.I.Sh");
});

test('bir kunda bir necha juftlik ochilmagan bo\'lsa — bitta qator, juftliklar birga', function () {
    // Sobirovga 09-22 kuni ikkala juftlik ham ochilmagan (baho yo'q, ariza yo'q)
    loSlot(1004, 'Sobirov Anvar', 502, 9002, 'Patologik anatomiya', '2026-09-22', '1');
    loSlot(1004, 'Sobirov Anvar', 502, 9002, 'Patologik anatomiya', '2026-09-22', '2');

    $report = app(LessonOpeningTeacherReport::class)->build();

    // Yig'mada bitta kun = bitta holat (juftliklar soniga qarab emas)
    $sobirov = collect($report['teachers'])->firstWhere('name', 'Sobirov Anvar');
    expect($sobirov)->toMatchArray(['total' => 1, 'no_request' => 1]);

    // Kunlar varag'ida ham bitta qator; qaysi juftlik va soat — o'sha katakda birga
    $rows = collect($report['days'])->where('teacher', 'Sobirov Anvar')->where('date', '2026-09-22')->values();
    expect($rows->count())->toBe(1);
    expect($rows->first()['pair'])->toBe('1-juftlik, 2-juftlik');
    expect($rows->first()['time'])->toBe('08:30-09:50, 10:00-11:20');
    expect($rows->first()['status'])->toBe('no_request');
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
        // Yangi sana oralig'i toolbari va uni boshlaydigan route
        ->toContain('Hisoblab, Excel yuklash')
        ->toContain('id="loExpFrom"')
        ->toContain('id="loExpTo"')
        ->toContain('loExportStart()')
        // Olib tashlangan elementlar
        ->not->toContain('Tasdiqlangach baho qo&#039;yish muddati')
        ->not->toContain("Tasdiqlangach baho qo'yish muddati")
        ->not->toContain('Test rejimi')
        ->not->toContain("eskilarini ko'rsatish");
});

// ─── Yangi qoida: barcha faol talaba baho/NB olishi shart (Dars belgilashdek) ───

test('qisman baholangan juftlik — bir faol talaba baholanmagan bo\'lsa baho qo\'yilmagan hisoblanadi', function () {
    $s1 = loGroup(701, '701-guruh');   // 1-talaba (baholanadi)
    $s2 = loExtraStudent(701);         // 2-talaba (baholanmaydi)
    loSlot(1701, 'Qisman Ustoz', 701, 9701, 'Q fan', '2026-09-15', '1');
    loGrade($s1, 9701, '2026-09-15', '1');   // faqat 1-talabaga baho

    loTeacher(1701, 'Qisman Ustoz');
    $teacher = App\Models\Teacher::where('hemis_id', 1701)->first();
    $days = app(TeacherMissedLessons::class)->forTeacher($teacher);

    expect($days->pluck('lesson_date')->all())->toBe(['2026-09-15']);
});

test('to\'liq baholangan juftlik — barcha faol talabaga baho qo\'yilgan bo\'lsa ro\'yxatda chiqmaydi', function () {
    $s1 = loGroup(702, '702-guruh');
    $s2 = loExtraStudent(702);
    loSlot(1702, 'To\'liq Ustoz', 702, 9702, 'T fan', '2026-09-15', '1');
    loGrade($s1, 9702, '2026-09-15', '1');
    loGrade($s2, 9702, '2026-09-15', '1');   // ikkala talabaga ham baho

    loTeacher(1702, 'To\'liq Ustoz');
    $teacher = App\Models\Teacher::where('hemis_id', 1702)->first();
    $days = app(TeacherMissedLessons::class)->forTeacher($teacher);

    expect($days)->toHaveCount(0);
});

test('NB yozuv sifatida sanaladi — bir talabaga baho, ikkinchisiga NB bo\'lsa baho qo\'yilgan hisoblanadi', function () {
    $s1 = loGroup(703, '703-guruh');
    $s2 = loExtraStudent(703);
    loSlot(1703, 'NB Ustoz', 703, 9703, 'N fan', '2026-09-15', '1');
    loGrade($s1, 9703, '2026-09-15', '1');   // 1-talabaga baho
    loNb($s2, 9703, '2026-09-15', '1');      // 2-talabaga NB

    loTeacher(1703, 'NB Ustoz');
    $teacher = App\Models\Teacher::where('hemis_id', 1703)->first();
    $days = app(TeacherMissedLessons::class)->forTeacher($teacher);

    expect($days)->toHaveCount(0);   // hamma faol talaba ishlov berilgan (baho/NB) → qo'yilgan
});
