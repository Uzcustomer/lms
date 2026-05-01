<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Computer;
use App\Models\ComputerAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComputerController extends Controller
{
    /**
     * Render the test centre layout: a 5-column × 15-row grid of computers
     * (some cells empty for aisles). Each populated cell shows the computer
     * number, IP, label, and current assignment (if any) for the chosen date.
     */
    public function index(Request $request)
    {
        $date = $request->input('date', now()->toDateString());

        $computers = Computer::orderBy('grid_column')
            ->orderBy('grid_row')
            ->get();

        // Build a 2D grid indexed by [row][col] for easy blade iteration.
        // Row 1 is the bottom of the room → render from top (row 15 → 1).
        $grid = [];
        for ($r = 15; $r >= 1; $r--) {
            for ($c = 1; $c <= 5; $c++) {
                $grid[$r][$c] = null;
            }
        }
        foreach ($computers as $pc) {
            if ($pc->grid_row && $pc->grid_column) {
                $grid[$pc->grid_row][$pc->grid_column] = $pc;
            }
        }

        // Active assignments for the chosen date (latest per computer)
        $assignments = ComputerAssignment::query()
            ->whereDate('planned_start', $date)
            ->with('student:hemis_id,full_name,group_name')
            ->orderBy('planned_start')
            ->get()
            ->groupBy('computer_number');

        return view('admin.computers.index', compact('computers', 'grid', 'assignments', 'date'));
    }

    /**
     * Inline edit a single computer's IP / MAC / label.
     */
    public function update(Request $request, Computer $computer): JsonResponse
    {
        $data = $request->validate([
            'ip_address' => 'nullable|string|max:45',
            'mac_address' => 'nullable|string|max:32',
            'label' => 'nullable|string|max:100',
            'grid_column' => 'nullable|integer|min:1|max:5',
            'grid_row' => 'nullable|integer|min:1|max:15',
            'active' => 'nullable|boolean',
        ]);

        $computer->fill($data);
        $computer->save();

        return response()->json(['ok' => true, 'computer' => $computer]);
    }
}
