<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class TriggerQuizSync extends Command
{
    protected $signature = 'quiz:trigger-moodle-sync';

    protected $description = 'Moodle serveriga SSH orqali ulanib quiz natijalar push skriptini ishga tushirish';

    public function handle(): int
    {
        $host = config('services.moodle.ssh_host');
        $user = config('services.moodle.ssh_user');
        $port = config('services.moodle.ssh_port', 22);
        $script = config('services.moodle.push_script');

        if (empty($host) || empty($user) || empty($script)) {
            $this->error('MOODLE_SSH_HOST, MOODLE_SSH_USER yoki MOODLE_PUSH_SCRIPT sozlanmagan.');
            Log::warning('quiz:trigger-moodle-sync — SSH konfiguratsiya to\'liq emas');
            return self::FAILURE;
        }

        $sshCommand = [
            'ssh',
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'ConnectTimeout=10',
            '-p', (string) $port,
            "{$user}@{$host}",
            "/usr/bin/php {$script}",
        ];

        $cmdString = implode(' ', $sshCommand);
        $this->info("Moodle ga SSH orqali ulanilmoqda: {$user}@{$host}:{$port}");
        $this->info("Skript: {$script}");
        Log::info("quiz:trigger-moodle-sync — SSH ishga tushirilmoqda: {$cmdString}");

        try {
            $process = new Process($sshCommand);
            $process->setTimeout(300); // 5 daqiqa

            $process->run(function ($type, $buffer) {
                if ($type === Process::ERR) {
                    $this->warn(trim($buffer));
                } else {
                    $this->line(trim($buffer));
                }
            });

            if ($process->isSuccessful()) {
                $this->info('Moodle push skript muvaffaqiyatli yakunlandi.');
                Log::info('quiz:trigger-moodle-sync — muvaffaqiyatli', [
                    'output' => substr($process->getOutput(), 0, 500),
                ]);
                return self::SUCCESS;
            }

            $this->error("SSH xato: exit code {$process->getExitCode()}");
            Log::error('quiz:trigger-moodle-sync — xato', [
                'exit_code' => $process->getExitCode(),
                'stderr' => substr($process->getErrorOutput(), 0, 500),
            ]);
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error("Xatolik: {$e->getMessage()}");
            Log::error('quiz:trigger-moodle-sync — exception', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}
