<?php

namespace App\Console\Commands;

use App\Services\HemisService;
use Illuminate\Console\Command;

class ImportStudentsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:import';

    /**
     * The console command description.
     *
     * @var string
     */


    protected $description = 'Import students from HEMIS';

    /**
     * Execute the console command.
     */
    public function handle(HemisService $hemisService)
    {
        $this->info('Starting student import...');
        $hemisService->importStudents();
        $this->info('Student import completed.');
    }
}
