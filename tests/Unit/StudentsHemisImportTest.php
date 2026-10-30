<?php

use App\Http\Controllers\Admin\StudentController;
use App\Jobs\ImportStudentsWithProgressJob;
use App\Services\HemisService;
use App\Services\TelegramService;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

/**
 * Talabalar sahifasidagi "HEMIS dan import" — navbatdagi job va keshdagi holat.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('cache.default', 'array');
    config()->set('services.hemis.token', 'test');
    Cache::forget(ImportStudentsWithProgressJob::PROGRESS_KEY);
});

function silentTelegram(): TelegramService
{
    return new class extends TelegramService {
        public array $sent = [];

        public function __construct()
        {
        }

        public function notify(string $message): void
        {
            $this->sent[] = $message;
        }
    };
}

function actAsImporter(bool $allowed = true): void
{
    test()->actingAs(new class ($allowed) extends User {
        public function __construct(private bool $allowed = true)
        {
        }

        public function hasAnyRole($roles): bool
        {
            return $this->allowed;
        }
    });
}

it('records page progress and the finish time', function () {
    $seen = [];
    $hemis = new class ($seen) extends HemisService {
        public function __construct(private array &$seen)
        {
        }

        public function importStudents(?callable $onPage = null): int
        {
            $onPage(1, 2, 200, 400);
            $this->seen[] = Cache::get(ImportStudentsWithProgressJob::PROGRESS_KEY);
            $onPage(2, 2, 400, 400);

            return 400;
        }
    };

    (new ImportStudentsWithProgressJob('Admin'))->handle($hemis, $telegram = silentTelegram());
    $state = Cache::get(ImportStudentsWithProgressJob::PROGRESS_KEY);

    expect($seen[0])->toMatchArray(['status' => 'running', 'page' => 1, 'pages' => 2, 'percent' => 50, 'imported' => 200])
        ->and($state)->toMatchArray(['status' => 'done', 'percent' => 100, 'imported' => 400, 'started_by' => 'Admin'])
        ->and($state['finished_at'])->not->toBeEmpty()
        ->and($state['message'])->toContain('400 ta talaba')
        ->and($telegram->sent)->toHaveCount(2);
});

it('marks the import as failed when HEMIS breaks off', function () {
    $hemis = new class extends HemisService {
        public function __construct()
        {
        }

        public function importStudents(?callable $onPage = null): int
        {
            $onPage(1, 5, 200, 1000);
            throw new RuntimeException('HEMIS student-list so\'rovi muvaffaqiyatsiz (sahifa 2)');
        }
    };

    (new ImportStudentsWithProgressJob)->handle($hemis, silentTelegram());
    $state = Cache::get(ImportStudentsWithProgressJob::PROGRESS_KEY);

    expect($state['status'])->toBe('error')
        ->and($state['percent'])->toBe(20)
        ->and($state['message'])->toContain('sahifa 2')
        ->and($state['finished_at'])->not->toBeEmpty();
});

it('queues one import at a time', function () {
    Queue::fake();
    actAsImporter();

    $first = (new StudentController)->startImport()->getData(true);
    $second = (new StudentController)->startImport()->getData(true);

    Queue::assertPushed(ImportStudentsWithProgressJob::class, 1);
    expect($first['already_running'])->toBeFalse()
        ->and($first['state']['status'])->toBe('queued')
        ->and($second['already_running'])->toBeTrue()
        ->and((new StudentController)->importStatus()->getData(true)['state']['status'])->toBe('queued');
});

it('restarts when the previous run went stale', function () {
    Queue::fake();
    actAsImporter();
    Cache::put(ImportStudentsWithProgressJob::PROGRESS_KEY, ['status' => 'running', 'updated_at' => now()->subMinutes(30)->toDateTimeString()]);

    expect((new StudentController)->startImport()->getData(true)['already_running'])->toBeFalse();
    Queue::assertPushed(ImportStudentsWithProgressJob::class, 1);
});

it('forbids other roles', function () {
    Queue::fake();
    actAsImporter(false);

    expect(fn () => (new StudentController)->startImport())
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    Queue::assertNothingPushed();
});
