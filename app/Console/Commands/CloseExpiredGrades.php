<?php

namespace App\Console\Commands;

use App\Models\StudentGrade;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CloseExpiredGrades extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grades:close-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deadline muddati o‘tgan student baholarining statusini closed qilish';

    public function __construct()
    {
        parent::__construct();
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        $expiredGrades = StudentGrade::where('deadline', '<', $now)
            ->where('status', '!=', 'closed')
            ->where('status', 'pending')
            ->update(['status' => 'closed']);

        $this->info("{$expiredGrades} ta yozuvning statusi 'closed' qilindi.");

        return 0;
    }
}
