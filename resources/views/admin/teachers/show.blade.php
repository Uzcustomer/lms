<x-app-layout>
    <x-slot name="header">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Xodim profili</h2>
            <a href="{{ route('admin.teachers.index') }}" class="back-link">
                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Xodimlar ro'yxati
            </a>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="max-w-full mx-auto sm:px-4 lg:px-6 mt-3">
            <div class="alert alert-success">{{ session('success') }}</div>
        </div>
    @endif
    @if (session('error'))
        <div class="max-w-full mx-auto sm:px-4 lg:px-6 mt-3">
            <div class="alert alert-error">{{ session('error') }}</div>
        </div>
    @endif
    @if ($errors->any())
        <div class="max-w-full mx-auto sm:px-4 lg:px-6 mt-3">
            <div class="alert alert-error">
                @foreach ($errors->all() as $error)
                    <span style="display: block;">{{ $error }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div style="padding: 16px 0;">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">

            {{-- PROFIL HEADER --}}
            <div class="profile-header">
                <div class="profile-header-inner">
                    <div class="profile-avatar-section">
                        @if($teacher->image)
                            <img src="{{ $teacher->image }}" alt="" class="profile-avatar">
                        @else
                            <div class="profile-avatar-placeholder">
                                <span>{{ mb_substr($teacher->first_name ?? '', 0, 1) }}{{ mb_substr($teacher->second_name ?? '', 0, 1) }}</span>
                            </div>
                        @endif
                        <div class="profile-name-section">
                            <h1 class="profile-name">{{ $teacher->full_name }}</h1>
                            <p class="profile-position">{{ $teacher->staff_position ?? '-' }}</p>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px;">
                                <span class="badge {{ $teacher->status ? 'badge-green' : 'badge-red' }}">
                                    {{ $teacher->status ? 'Faol' : 'Nofaol' }}
                                </span>
                                @if(!$teacher->is_active)
                                    <span class="badge badge-yellow">HEMIS'da yo'q</span>
                                @endif
                                @foreach($teacher->roles as $role)
                                    <span class="badge badge-indigo">
                                        {{ \App\Enums\ProjectRole::tryFrom($role->name)?->label() ?? $role->name }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 16px; flex-shrink: 0;">
                        <div class="profile-id-section">
                            <span class="profile-id-label">ID RAQAMI</span>
                            <span class="profile-id-value">{{ $teacher->employee_id_number }}</span>
                        </div>
                        <form action="{{ route('admin.teachers.reset-password', $teacher) }}" method="POST"
                              onsubmit="return confirm('Parolni tiklashni tasdiqlaysizmi? Yangi parol: tug\'ilgan sana (ddmmyyyy)')">
                            @csrf
                            <button type="submit" class="btn-reset-password" {{ !$teacher->birth_date ? 'disabled' : '' }}>
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                                Parolni tiklash
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ASOSIY GRID --}}
            <div class="profile-grid">

                {{-- 1. Shaxsiy ma'lumotlar --}}
                <div class="card card-blue">
                    <div class="card-header card-header-blue">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Shaxsiy ma'lumotlar
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Kafedra</span>
                            <span class="info-value">{{ $teacher->department ?? '-' }}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Jinsi</span>
                            <span class="info-value">{{ $teacher->gender ?? '-' }}</span>
                        </div>
                        @if($teacher->birth_date)
                        <div class="info-row">
                            <span class="info-label">Tug'ilgan sana</span>
                            <span class="info-value">{{ \Carbon\Carbon::parse($teacher->birth_date)->format('d.m.Y') }}</span>
                        </div>
                        @endif
                        <div class="info-row">
                            <span class="info-label">Ish turi</span>
                            <span class="info-value">{{ $teacher->employment_form ?? '-' }}</span>
                        </div>
                        @if($teacher->employee_type)
                        <div class="info-row">
                            <span class="info-label">Xodim turi</span>
                            <span class="info-value">{{ $teacher->employee_type }}</span>
                        </div>
                        @endif
                        @if($teacher->employee_status)
                        <div class="info-row">
                            <span class="info-label">Holati</span>
                            <span class="info-value">{{ $teacher->employee_status }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- 2. Aloqa ma'lumotlari --}}
                <div class="card card-teal">
                    <div class="card-header card-header-teal">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        Aloqa ma'lumotlari
                        @if(auth()->user()->hasAnyRole(['superadmin', 'admin', 'kichik_admin']))
                            <button type="button" onclick="toggleContactEdit()" class="contact-edit-toggle" id="contact-edit-btn" title="Tahrirlash">
                                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                    <div class="card-body">
                        {{-- Ko'rish rejimi --}}
                        <div id="contact-view">
                            <div class="contact-item">
                                <div class="contact-icon contact-icon-phone">
                                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="contact-label">Telefon</div>
                                    <div class="contact-value {{ $teacher->phone ? '' : 'contact-missing' }}">
                                        {{ $teacher->phone ?? 'Kiritilmagan' }}
                                    </div>
                                </div>
                            </div>
                            <div class="contact-item">
                                <div class="contact-icon contact-icon-telegram">
                                    <svg style="width: 18px; height: 18px;" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                    </svg>
                                </div>
                                <div>
                                    <div class="contact-label">
                                        Telegram
                                        @if($teacher->telegram_verified_at)
                                            <svg style="width: 14px; height: 14px; display: inline; color: #10b981; margin-left: 4px;" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        @elseif($teacher->telegram_username)
                                            <span style="font-size: 10px; color: #f59e0b; margin-left: 4px;">tasdiqlanmagan</span>
                                        @endif
                                    </div>
                                    <div class="contact-value {{ $teacher->telegram_username ? '' : 'contact-missing' }}">
                                        {{ $teacher->telegram_username ?? 'Kiritilmagan' }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Tahrirlash rejimi (faqat admin rollar uchun) --}}
                        @if(auth()->user()->hasAnyRole(['superadmin', 'admin', 'kichik_admin']))
                        <form action="{{ route('admin.teachers.update-contact', $teacher) }}" method="POST" id="contact-edit" style="display: none;">
                            @csrf
                            @method('PUT')
                            <div class="contact-edit-field">
                                <div class="contact-icon contact-icon-phone" style="width: 34px; height: 34px;">
                                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                    </svg>
                                </div>
                                <div style="flex: 1;">
                                    <label class="form-label" style="margin-bottom: 2px;">Telefon</label>
                                    <input type="text" name="phone" value="{{ $teacher->phone }}" placeholder="+998901234567" class="form-input">
                                </div>
                            </div>
                            <div class="contact-edit-field">
                                <div class="contact-icon contact-icon-telegram" style="width: 34px; height: 34px;">
                                    <svg style="width: 16px; height: 16px;" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                    </svg>
                                </div>
                                <div style="flex: 1;">
                                    <label class="form-label" style="margin-bottom: 2px;">Telegram username</label>
                                    <input type="text" name="telegram_username" value="{{ $teacher->telegram_username }}" placeholder="@username" class="form-input">
                                </div>
                            </div>
                            <div style="display: flex; gap: 8px; margin-top: 10px;">
                                <button type="submit" class="btn btn-teal" style="flex: 1;">
                                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Saqlash
                                </button>
                                <button type="button" onclick="toggleContactEdit()" class="btn btn-gray">Bekor</button>
                            </div>
                        </form>
                        @endif
                    </div>
                </div>

                {{-- 3. Hisob sozlamalari --}}
                <div class="card card-violet">
                    <div class="card-header card-header-violet">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Hisob sozlamalari
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.teachers.update', $teacher) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label class="form-label">Login</label>
                                    <input type="text" name="login" value="{{ old('login', $teacher->login) }}" class="form-input">
                                </div>
                                <div>
                                    <label class="form-label">Yangi parol</label>
                                    <input type="password" name="password" placeholder="O'zgarmaydi" class="form-input">
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                                <div style="flex: 1;">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-input">
                                        <option value="1" {{ $teacher->status ? 'selected' : '' }}>Faol</option>
                                        <option value="0" {{ !$teacher->status ? 'selected' : '' }}>Nofaol</option>
                                    </select>
                                </div>
                                <div style="flex: 1; display: flex; align-items: flex-end;">
                                    <button type="submit" class="btn btn-dark" style="width: 100%; margin-top: 18px;">
                                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Saqlash
                                    </button>
                                </div>
                            </div>
                        </form>

                        @if($teacher->must_change_password)
                            <div class="pw-warning" style="margin-top: 10px;">
                                <svg style="width: 14px; height: 14px; flex-shrink: 0;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                Parol tiklangan. Keyingi kirishda o'zgartirishi kerak.
                            </div>
                        @endif
                    </div>
                </div>

                {{-- 4. Rollar --}}
                <div class="card card-amber">
                    <div class="card-header card-header-amber">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Rollarni boshqarish
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.teachers.update-roles', $teacher) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="roles-grid">
                                @foreach($roles as $role)
                                    <label class="role-card {{ $teacher->hasRole($role->value) ? 'role-active' : '' }}">
                                        <input type="checkbox" name="roles[]" value="{{ $role->value }}"
                                               {{ $teacher->hasRole($role->value) ? 'checked' : '' }}
                                               onchange="toggleRole(this)" style="display: none;">
                                        <div class="role-icon {{ $teacher->hasRole($role->value) ? 'role-icon-active' : '' }}" data-icon>
                                            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                        </div>
                                        <span class="role-name">{{ $role->label() }}</span>
                                        <div class="check-indicator {{ $teacher->hasRole($role->value) ? '' : 'hidden' }}">
                                            <svg style="width: 14px; height: 14px; color: #f59e0b;" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                    </label>
                                @endforeach
                            </div>

                            <div id="department-section" style="display: none; margin-top: 10px;">
                                <div style="padding: 10px; background: #eff6ff; border-radius: 8px; border: 1px solid #bfdbfe;">
                                    <label style="font-size: 11px; font-weight: 600; color: #1e40af; display: block; margin-bottom: 4px;">Dekan roli uchun fakultet:</label>
                                    <select name="department_hemis_id" id="department_hemis_id" class="form-input">
                                        <option value="">-- Tanlang --</option>
                                        @foreach($departments as $department)
                                            <option value="{{ $department->department_hemis_id }}"
                                                {{ $teacher->department_hemis_id == $department->department_hemis_id ? 'selected' : '' }}>
                                                {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div style="margin-top: 10px; display: flex; justify-content: flex-end;">
                                <button type="submit" class="btn btn-amber">
                                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Rollarni saqlash
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function toggleContactEdit() {
            var view = document.getElementById('contact-view');
            var edit = document.getElementById('contact-edit');
            var btn = document.getElementById('contact-edit-btn');
            if (edit.style.display === 'none') {
                view.style.display = 'none';
                edit.style.display = 'block';
                btn.classList.add('contact-edit-active');
            } else {
                view.style.display = 'block';
                edit.style.display = 'none';
                btn.classList.remove('contact-edit-active');
            }
        }

        function toggleRole(checkbox) {
            var card = checkbox.closest('.role-card');
            var icon = card.querySelector('[data-icon]');
            var check = card.querySelector('.check-indicator');

            card.classList.toggle('role-active', checkbox.checked);
            icon.classList.toggle('role-icon-active', checkbox.checked);
            check.classList.toggle('hidden', !checkbox.checked);

            var dekanCheckbox = document.querySelector('input[value="dekan"]');
            var dept = document.getElementById('department-section');
            if (dept && dekanCheckbox) {
                dept.style.display = dekanCheckbox.checked ? 'block' : 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            var dekanCheckbox = document.querySelector('input[value="dekan"]');
            var dept = document.getElementById('department-section');
            if (dept && dekanCheckbox) {
                dept.style.display = dekanCheckbox.checked ? 'block' : 'none';
            }
        });
    </script>

    <style>
        /* ===== Back Link ===== */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            text-decoration: none;
            transition: color 0.2s;
        }
        .back-link:hover { color: #1e293b; }

        /* ===== Alerts ===== */
        .alert { padding: 10px 16px; border-radius: 8px; font-size: 13px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* ===== Profile Header ===== */
        .profile-header {
            background: linear-gradient(135deg, #1a3268 0%, #2b5ea7 50%, #3b7ddb 100%);
            border-radius: 16px;
            padding: 3px;
            margin-bottom: 16px;
        }
        .profile-header-inner {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(248,250,252,0.98) 100%);
            border-radius: 14px;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .profile-avatar-section {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
            min-width: 0;
        }
        .profile-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #2b5ea7;
            box-shadow: 0 4px 12px rgba(43,94,167,0.2);
            flex-shrink: 0;
        }
        .profile-avatar-placeholder {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a3268, #2b5ea7);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(43,94,167,0.2);
        }
        .profile-avatar-placeholder span {
            color: #ffffff;
            font-size: 22px;
            font-weight: 700;
        }
        .profile-name {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            line-height: 1.3;
        }
        .profile-position {
            font-size: 13px;
            color: #64748b;
            margin: 2px 0 0;
        }
        .profile-id-section {
            text-align: right;
            flex-shrink: 0;
        }
        .profile-id-label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .profile-id-value {
            display: block;
            font-size: 18px;
            font-weight: 800;
            color: #2b5ea7;
            font-family: monospace;
            letter-spacing: 0.05em;
        }

        /* ===== Badges ===== */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.5;
        }
        .badge-green { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-red { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-yellow { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
        .badge-indigo {
            background: linear-gradient(135deg, #1a3268, #2b5ea7);
            color: #ffffff;
            border: none;
        }

        /* ===== Grid ===== */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        @media (max-width: 768px) {
            .profile-grid { grid-template-columns: 1fr; }
            .profile-header-inner { flex-direction: column; text-align: center; }
            .profile-avatar-section { flex-direction: column; }
            .profile-id-section { text-align: center; }
        }

        /* ===== Cards ===== */
        .card {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        .card-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .card-header-blue { background: linear-gradient(135deg, #dbeafe, #eff6ff); color: #1e40af; border-bottom: 2px solid #bfdbfe; }
        .card-header-teal { background: linear-gradient(135deg, #ccfbf1, #f0fdfa); color: #0f766e; border-bottom: 2px solid #99f6e4; }
        .card-header-violet { background: linear-gradient(135deg, #ede9fe, #f5f3ff); color: #5b21b6; border-bottom: 2px solid #ddd6fe; }
        .card-header-amber { background: linear-gradient(135deg, #fef3c7, #fffbeb); color: #92400e; border-bottom: 2px solid #fde68a; }
        .card-body { padding: 14px 16px; }

        /* ===== Info Rows ===== */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 7px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
            flex-shrink: 0;
            margin-right: 12px;
        }
        .info-value {
            font-size: 12.5px;
            color: #1e293b;
            font-weight: 600;
            text-align: right;
        }

        /* ===== Contact Items ===== */
        .contact-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px;
            border-radius: 10px;
            background: #f8fafc;
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        .contact-item:last-child { margin-bottom: 0; }
        .contact-item:hover { border-color: #94a3b8; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .contact-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .contact-icon-phone { background: linear-gradient(135deg, #10b981, #059669); color: #fff; }
        .contact-icon-telegram { background: linear-gradient(135deg, #0ea5e9, #0284c7); color: #fff; }
        .contact-edit-toggle {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid #99f6e4;
            background: #f0fdfa;
            color: #0f766e;
            cursor: pointer;
            transition: all 0.2s;
        }
        .contact-edit-toggle:hover { background: #ccfbf1; border-color: #5eead4; }
        .contact-edit-active { background: #0f766e !important; color: #fff !important; border-color: #0f766e !important; }
        .contact-edit-field {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
        }
        .contact-label { font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.03em; }
        .contact-value { font-size: 14px; font-weight: 700; color: #1e293b; margin-top: 2px; }
        .contact-missing { color: #ef4444; font-weight: 500; }

        /* ===== Form ===== */
        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .form-input {
            width: 100%;
            height: 34px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0 10px;
            font-size: 13px;
            color: #1e293b;
            background: #ffffff;
            outline: none;
            transition: all 0.2s;
        }
        .form-input:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139,92,246,0.1);
        }

        /* ===== Buttons ===== */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-teal { background: linear-gradient(135deg, #14b8a6, #0d9488); color: #fff; }
        .btn-teal:hover { box-shadow: 0 2px 8px rgba(20,184,166,0.4); }
        .btn-gray { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
        .btn-gray:hover { background: #e2e8f0; color: #334155; }
        .btn-dark { background: #1e293b; color: #fff; }
        .btn-dark:hover { background: #0f172a; }
        .btn-orange { background: linear-gradient(135deg, #f97316, #ea580c); color: #fff; white-space: nowrap; }
        .btn-orange:hover { box-shadow: 0 2px 8px rgba(249,115,22,0.4); }
        .btn-orange:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-amber { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; }
        .btn-amber:hover { box-shadow: 0 2px 8px rgba(245,158,11,0.4); }

        /* ===== Reset Password Button (header) ===== */
        .btn-reset-password {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 18px;
            font-size: 12px;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(135deg, #f97316, #ea580c);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(249,115,22,0.3);
        }
        .btn-reset-password:hover {
            box-shadow: 0 4px 16px rgba(249,115,22,0.5);
            transform: translateY(-1px);
        }
        .btn-reset-password:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ===== Password Warning ===== */
        .pw-warning {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 10px;
            background: #fef9c3;
            border: 1px solid #fde68a;
            border-radius: 8px;
            font-size: 11px;
            color: #854d0e;
            margin-bottom: 10px;
        }

        /* ===== Roles ===== */
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        @media (max-width: 768px) {
            .roles-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .role-card {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 10px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.15s;
            background: #ffffff;
        }
        .role-card:hover { border-color: #cbd5e1; background: #f8fafc; }
        .role-active {
            border-color: #f59e0b !important;
            background: #fffbeb !important;
        }
        .role-icon {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #f1f5f9;
            color: #94a3b8;
            transition: all 0.15s;
        }
        .role-icon-active {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            color: #fff !important;
        }
        .role-name { font-size: 11.5px; font-weight: 600; color: #334155; flex: 1; }
        .hidden { display: none !important; }
    </style>
</x-app-layout>
