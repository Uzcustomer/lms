<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auditorium;
use App\Models\Beacon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Admin CRUD for classroom BLE beacons (the UI counterpart of attendance:beacon-add). */
class BeaconController extends Controller
{
    private const UUID_RE = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    public function index()
    {
        $beacons = Beacon::orderBy('auditorium_code')->orderBy('minor')->get();

        $lastSeen = DB::table('presence_logs')
            ->select('beacon_id', DB::raw('MAX(seen_at) as last_seen'), DB::raw('COUNT(DISTINCT student_id) as students'))
            ->groupBy('beacon_id')
            ->get()
            ->keyBy('beacon_id');

        $auditoriums = Auditorium::where('active', true)
            ->orderBy('building_name')
            ->orderBy('name')
            ->get(['code', 'name', 'building_name']);

        return view('admin.beacons.index', compact('beacons', 'lastSeen', 'auditoriums'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if (Beacon::matching($data['uuid'], $data['major'], $data['minor'])->exists()) {
            throw ValidationException::withMessages(['uuid' => 'Bu UUID/Major/Minor kombinatsiyasi allaqachon ro\'yxatda.']);
        }

        Beacon::create($data + ['active' => true]);

        return redirect()->route('admin.beacons.index')->with('success', 'Beacon qo\'shildi.');
    }

    public function update(Request $request, Beacon $beacon)
    {
        $data = $this->validated($request);
        $duplicate = Beacon::matching($data['uuid'], $data['major'], $data['minor'])
            ->where('id', '!=', $beacon->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['uuid' => 'Bu UUID/Major/Minor kombinatsiyasi boshqa beacon\'da bor.']);
        }

        $beacon->update($data);

        return redirect()->route('admin.beacons.index')->with('success', 'Beacon yangilandi.');
    }

    public function toggle(Beacon $beacon)
    {
        $beacon->update(['active' => !$beacon->active]);

        return redirect()->route('admin.beacons.index')
            ->with('success', $beacon->active ? 'Beacon yoqildi.' : 'Beacon o\'chirildi.');
    }

    public function destroy(Beacon $beacon)
    {
        $beacon->delete();

        return redirect()->route('admin.beacons.index')->with('success', 'Beacon o\'chirib tashlandi.');
    }

    /** @return array{uuid: string, major: int, minor: int, auditorium_code: string, auditorium_name: ?string, label: ?string} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'uuid' => ['required', 'string', 'regex:' . self::UUID_RE],
            'major' => ['required', 'integer', 'min:0', 'max:65535'],
            'minor' => ['required', 'integer', 'min:0', 'max:65535'],
            'auditorium_code' => ['required', 'string', 'max:64', 'exists:auditoriums,code'],
            'label' => ['nullable', 'string', 'max:120'],
        ], [
            'uuid.regex' => 'UUID formati noto\'g\'ri (masalan fda50693-a4e2-4fb1-afcf-c6eb07647825).',
            'auditorium_code.exists' => 'Bunday xona kodi topilmadi.',
        ]);

        return [
            'uuid' => strtolower(trim($data['uuid'])),
            'major' => (int) $data['major'],
            'minor' => (int) $data['minor'],
            'auditorium_code' => trim($data['auditorium_code']),
            'auditorium_name' => Auditorium::where('code', trim($data['auditorium_code']))->value('name'),
            'label' => $data['label'] ?? null,
        ];
    }
}
