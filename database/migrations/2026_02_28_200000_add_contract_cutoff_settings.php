<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Kontrakt to'lov muddatlari: JSON formatda saqlanadi
        // Har bir element: {deadline: "YYYY-MM-DD", percent: N}
        $defaultCutoffs = json_encode([
            ['deadline' => '2025-10-01', 'percent' => 25],
            ['deadline' => '2026-01-01', 'percent' => 50],
            ['deadline' => '2026-03-01', 'percent' => 75],
            ['deadline' => '2026-05-01', 'percent' => 100],
        ]);

        Setting::set('contract_cutoffs', $defaultCutoffs);
    }

    public function down(): void
    {
        Setting::where('key', 'contract_cutoffs')->delete();
    }
};
