<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('telegram_deadline_days', '7');
    }

    public function down(): void
    {
        Setting::where('key', 'telegram_deadline_days')->delete();
    }
};
