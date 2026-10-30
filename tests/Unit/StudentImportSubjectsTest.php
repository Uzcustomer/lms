<?php

use App\Http\Controllers\Admin\StudentController;
use App\Models\Student;
use App\Services\HemisService;

/**
 * Talabalar ro'yxatidagi "Fanlari" tugmasi — faqat shu talabaning fanlarini HEMIS dan yuklaydi.
 */

uses(Tests\TestCase::class);

beforeEach(fn () => config()->set('services.hemis.token', 'test'));

function fakeHemis(int $result): HemisService
{
    return new class ($result) extends HemisService {
        public array $calls = [];

        public function __construct(private int $result)
        {
        }

        public function importStudentSubjects(?callable $onProgress = null, ?array $onlyHemisIds = null): int
        {
            $this->calls[] = $onlyHemisIds;

            return $this->result;
        }
    };
}

it('imports subjects for the single student', function () {
    $hemis = fakeHemis(14);
    $student = new Student(['hemis_id' => 10948, 'full_name' => 'Abdulxakimova A']);

    $data = (new StudentController)->importSubjects($student, $hemis)->getData(true);

    expect($hemis->calls)->toBe([[10948]])
        ->and($data['ok'])->toBeTrue()
        ->and($data['imported'])->toBe(14)
        ->and($data['message'])->toContain('14 ta fan');
});

it('reports when HEMIS returns nothing', function () {
    $student = new Student(['hemis_id' => 10948, 'full_name' => 'Abdulxakimova A']);

    $data = (new StudentController)->importSubjects($student, fakeHemis(0))->getData(true);

    expect($data['ok'])->toBeFalse()->and($data['message'])->toContain('fan kelmadi');
});

it('refuses a student without a HEMIS id', function () {
    $response = (new StudentController)->importSubjects(new Student(['full_name' => 'X']), fakeHemis(5));

    expect($response->getStatusCode())->toBe(422);
});
