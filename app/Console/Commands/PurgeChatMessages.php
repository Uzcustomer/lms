<?php

namespace App\Console\Commands;

use App\Models\ChatMessage;
use Illuminate\Console\Command;

class PurgeChatMessages extends Command
{
    protected $signature = 'chat:purge {--days=14 : Days to keep}';
    protected $description = 'Delete chat messages older than N days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $deleted = ChatMessage::where('created_at', '<', now()->subDays($days))->delete();
        $this->info("Deleted {$deleted} chat messages older than {$days} days.");
        return 0;
    }
}
