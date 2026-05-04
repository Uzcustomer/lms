{{-- Statistika sahifasi uchun qo'shimcha filtr maydonlari (cascade form ichida) --}}
<div class="rf-item" style="min-width: 160px;">
    <label class="rf-label"><span class="rf-dot" style="background:#ef4444;"></span> Holat</label>
    <select name="final_status" class="rf-select2" style="width: 100%;">
        <option value="">Barchasi</option>
        <option value="pending" @selected(($filters['final_status'] ?? '') === 'pending')>Kutilmoqda</option>
        <option value="approved" @selected(($filters['final_status'] ?? '') === 'approved')>Tasdiqlangan</option>
        <option value="rejected" @selected(($filters['final_status'] ?? '') === 'rejected')>Rad etilgan</option>
    </select>
</div>

<div class="rf-item" style="min-width: 150px;">
    <label class="rf-label"><span class="rf-dot" style="background:#0ea5e9;"></span> Sanadan</label>
    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="rf-input">
</div>

<div class="rf-item" style="min-width: 150px;">
    <label class="rf-label"><span class="rf-dot" style="background:#0284c7;"></span> Sanagacha</label>
    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="rf-input">
</div>
