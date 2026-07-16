@csrf
@if(isset($indicator))
    @method('PUT')
@endif

@if($errors->any())
    <div class="bg-red-100 border border-red-400 text-red-800 px-4 py-3 rounded mb-4">
        <ul class="list-disc list-inside text-sm">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php $v = fn($field, $default = null) => old($field, isset($indicator) ? $indicator->{$field} : $default); @endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Qabul yili <span class="text-rose-500">*</span></label>
        <input type="number" name="qabul_yili" value="{{ $v('qabul_yili') }}" min="1900" max="2100" required
               class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Ta'lim turi</label>
        <select name="talim_turi" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            <option value="">— tanlang —</option>
            @foreach($talimTurlari as $t)
                <option value="{{ $t }}" @selected($v('talim_turi') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Ta'lim shakli</label>
        <select name="talim_shakli" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            <option value="">— tanlang —</option>
            @foreach($talimShakllari as $t)
                <option value="{{ $t }}" @selected($v('talim_shakli') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">To'lov shakli</label>
        <select name="tolov_shakli" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
            <option value="">— tanlang —</option>
            @foreach($tolovShakllari as $t)
                <option value="{{ $t }}" @selected($v('tolov_shakli') === $t)>{{ $t }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">Mutaxassislik / yo'nalish</label>
        <input type="text" name="mutaxassislik" value="{{ $v('mutaxassislik') }}"
               class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Mutaxassislik kodi</label>
        <input type="text" name="mutaxassislik_kodi" value="{{ $v('mutaxassislik_kodi') }}"
               class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Eng past o'tish bali</label>
        <input type="number" step="0.1" name="min_ball" value="{{ $v('min_ball') }}" min="0" max="1000"
               class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Reja (kvota)</label>
        <input type="number" name="reja" value="{{ $v('reja') }}" min="0"
               class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Qabul qilinganlar soni</label>
        <input type="number" name="qabul_soni" value="{{ $v('qabul_soni') }}" min="0"
               class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">
    </div>
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-1">Izoh</label>
        <textarea name="izoh" rows="3" class="w-full rounded-xl border-slate-300 focus:border-sky-500 focus:ring-sky-500">{{ $v('izoh') }}</textarea>
    </div>
</div>

<div class="flex items-center gap-2 mt-6">
    <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg">Saqlash</button>
    <a href="{{ route('admin.admission-indicators.index') }}" class="px-5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg">Bekor qilish</a>
</div>
