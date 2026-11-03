<?php

namespace App\Http\Middleware;

use App\Models\Auditorium;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Dars jadvali: nofaol xona (auditoriums.active = false) biriktirilmaydi va saqlanmaydi.
 *
 * Barcha o'zgartiruvchi so'rovlar (assign-room, cycle-assign, week-override, cards/{id}/update, Takliflar apply/propose,
 * xona so'rovi va h.k.) — `auditorium_code` va `actions.*.auditorium_code` faol xona bo'lishi shart; nofaol bo'lsa 422
 * "Xona nofaol — boshqa xona tanlang". Bo'sh (null) — ruxsat (xonani olib tashlash). Xonalar ro'yxatini tahrirlash
 * (auditoriums.*) tekshirilmaydi.
 */
class TimetableActiveRoom
{
    public const MESSAGE = 'Xona nofaol — boshqa xona tanlang';

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethodSafe() || $request->routeIs('admin.timetable.auditoriums*')) {
            return $next($request);
        }
        if ($bad = self::inactiveIn($request->all())) {
            return response()->json(['error' => self::message($bad), 'code' => 'room_inactive', 'auditorium_code' => $bad->code], 422);
        }

        return $next($request);
    }

    /**
     * So'rov ma'lumotidagi (auditorium_code, actions.*.auditorium_code) birinchi nofaol xona yoki null. Tasdiqlangan so'rov qayta
     * bajarilganda ham (TimetableChangeApproval::applyLocked — middleware'siz) chaqiriladi: kutish paytida xona nofaol bo'lib qolishi mumkin.
     */
    public static function inactiveIn(array $payload): ?Auditorium
    {
        if (!Schema::hasColumn('auditoriums', 'active')) {
            return null;
        }
        $codes = [];
        $one = $payload['auditorium_code'] ?? null;
        if (is_string($one) && trim($one) !== '') {
            $codes[] = trim($one);
        }
        foreach ((array) ($payload['actions'] ?? []) as $a) {
            $c = is_array($a) ? ($a['auditorium_code'] ?? null) : null;
            if (is_string($c) && trim($c) !== '') {
                $codes[] = trim($c);
            }
        }

        return $codes ? Auditorium::whereIn('code', array_unique($codes))->where('active', false)->first(['code', 'name']) : null;
    }

    public static function message(Auditorium $room): string
    {
        return self::MESSAGE . ' («' . ($room->name ?: $room->code) . '»).';
    }
}
