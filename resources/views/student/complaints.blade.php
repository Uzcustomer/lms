<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Shikoyatlar') }}
        </h2>
    </x-slot>

    <style>
        .cp { max-width: 760px; margin: 0 auto; padding: 0 12px 28px; color: #1e293b; }
        .cp-alert { margin-bottom: 14px; padding: 12px 14px; border-radius: 10px; font-size: 14px; }
        .cp-alert.ok { background: #ecfdf5; border: 1px solid #86efac; color: #166534; }
        .cp-alert.bad { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .cp-alert ul { margin: 0; padding-left: 18px; }
        .cp-card { margin-bottom: 16px; padding: 18px; border: 1px solid #e5e7eb; border-radius: 14px; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .cp-card h3 { margin: 0 0 4px; font-size: 16px; font-weight: 700; color: #0f2748; }
        .cp-lead { margin: 0 0 14px; color: #64748b; font-size: 13px; }
        .cp-label { display: block; margin: 12px 0 6px; font-size: 13px; font-weight: 600; color: #334155; }
        .cp-label b { color: #dc2626; }
        .cp-input, .cp-text {
            width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px;
            font-family: inherit; font-size: 15px; color: #0f172a; background: #fff; box-sizing: border-box;
        }
        .cp-text { min-height: 130px; resize: vertical; line-height: 1.5; }
        .cp-input:focus, .cp-text:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,.15); }
        .cp-drop {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            padding: 14px; border: 2px dashed #cbd5e1; border-radius: 10px;
            color: #475569; font-size: 14px; font-weight: 600; cursor: pointer; background: #f8fafc;
        }
        .cp-drop:hover { border-color: #4f46e5; color: #4f46e5; }
        .cp-hint { margin-top: 6px; color: #94a3b8; font-size: 12px; }
        .cp-previews { display: grid; grid-template-columns: repeat(auto-fill, minmax(84px, 1fr)); gap: 8px; margin-top: 10px; }
        .cp-previews div { position: relative; aspect-ratio: 1; border-radius: 8px; overflow: hidden; background: #f1f5f9; }
        .cp-previews img { width: 100%; height: 100%; object-fit: cover; }
        .cp-previews button {
            position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border: 0; border-radius: 50%;
            background: rgba(15,23,42,.75); color: #fff; font-size: 14px; line-height: 22px; cursor: pointer;
        }
        .cp-submit {
            width: 100%; margin-top: 16px; padding: 12px; border: 0; border-radius: 10px;
            background: #4f46e5; color: #fff; font-family: inherit; font-size: 15px; font-weight: 700; cursor: pointer;
        }
        .cp-submit:disabled { background: #a5b4fc; cursor: wait; }
        .cp-item { padding: 14px 0; border-top: 1px solid #f1f5f9; }
        .cp-item:first-of-type { border-top: 0; padding-top: 4px; }
        .cp-item-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
        .cp-date { color: #94a3b8; font-size: 12px; }
        .cp-chip { padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .cp-chip.new { background: #fef3c7; color: #92400e; }
        .cp-chip.done { background: #dcfce7; color: #166534; }
        .cp-msg { margin: 0; color: #334155; font-size: 14px; line-height: 1.5; white-space: pre-line; word-break: break-word; }
        .cp-thumbs { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .cp-thumbs a { display: block; width: 64px; height: 64px; border-radius: 8px; overflow: hidden; background: #f1f5f9; }
        .cp-thumbs img { width: 100%; height: 100%; object-fit: cover; }
    </style>

    <div class="cp">
        @if(session('success'))
            <div class="cp-alert ok">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="cp-alert bad">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="cp-card">
            <h3>{{ __('Shikoyat yuborish') }}</h3>
            <p class="cp-lead">{{ __("Muammoingizni yozing va kerak bo'lsa rasm biriktiring. Shikoyatingiz mas'ul xodimlarga yuboriladi, ular siz bilan telefon orqali bog'lanadi.") }}</p>

            <form method="POST" action="{{ route('student.complaints.store') }}" enctype="multipart/form-data" id="cpForm">
                @csrf
                <label class="cp-label" for="cp-phone">{{ __('Telefon raqamingiz') }} <b>*</b></label>
                <input class="cp-input" id="cp-phone" name="phone" type="tel" required maxlength="32"
                       value="{{ old('phone', $student->phone) }}" placeholder="+998 90 123 45 67" autocomplete="tel">

                <label class="cp-label" for="cp-message">{{ __('Muammo') }} <b>*</b></label>
                <textarea class="cp-text" id="cp-message" name="message" required minlength="10" maxlength="5000"
                          placeholder="{{ __('Muammoni batafsil yozing') }}">{{ old('message') }}</textarea>

                <label class="cp-label" for="cp-images">{{ __('Rasmlar') }}</label>
                <label class="cp-drop" for="cp-images">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.16-5.16a2.25 2.25 0 013.18 0l5.16 5.16m-1.5-1.5l1.41-1.41a2.25 2.25 0 013.18 0l2.91 2.91M3.75 21h16.5A1.5 1.5 0 0021.75 19.5V4.5A1.5 1.5 0 0020.25 3H3.75A1.5 1.5 0 002.25 4.5v15A1.5 1.5 0 003.75 21z"/></svg>
                    {{ __('Rasm tanlash') }}
                </label>
                <input type="file" id="cp-images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple style="display:none">
                <div class="cp-hint">{{ __("Ko'pi bilan :max ta rasm, har biri 5 MB gacha (jpg, png, webp).", ['max' => $maxImages]) }}</div>
                <div class="cp-previews" id="cpPreviews"></div>

                <button type="submit" class="cp-submit" id="cpSubmit">{{ __('Yuborish') }}</button>
            </form>
        </div>

        <div class="cp-card">
            <h3>{{ __('Mening shikoyatlarim') }}</h3>
            @forelse($complaints as $complaint)
                <div class="cp-item">
                    <div class="cp-item-head">
                        <span class="cp-date">#{{ $complaint->id }} · {{ $complaint->created_at->format('d.m.Y H:i') }}</span>
                        @if($complaint->isResolved())
                            <span class="cp-chip done">{{ __('Hal etildi') }}</span>
                        @else
                            <span class="cp-chip new">{{ __("Ko'rib chiqilmoqda") }}</span>
                        @endif
                    </div>
                    <p class="cp-msg">{{ $complaint->message }}</p>
                    @if(!empty($complaint->images))
                        <div class="cp-thumbs">
                            @foreach($complaint->images as $i => $path)
                                <a href="{{ route('student.complaints.image', [$complaint, $i]) }}" target="_blank">
                                    <img src="{{ route('student.complaints.image', [$complaint, $i]) }}" alt="" loading="lazy">
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <p class="cp-lead" style="margin:0">{{ __("Hali shikoyat yubormagansiz.") }}</p>
            @endforelse
        </div>
    </div>

    <script>
    (function () {
        const MAX = {{ (int) $maxImages }};
        const MAX_BYTES = 5 * 1024 * 1024;
        const input = document.getElementById('cp-images');
        const box = document.getElementById('cpPreviews');
        let files = [];

        function sync() {
            // Tanlangan fayllar ro'yxatini inputga qaytaramiz (o'chirilganlar chiqib ketadi)
            const dt = new DataTransfer();
            files.forEach(f => dt.items.add(f));
            input.files = dt.files;

            box.innerHTML = '';
            files.forEach((file, i) => {
                const cell = document.createElement('div');
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.alt = '';
                const del = document.createElement('button');
                del.type = 'button';
                del.textContent = '×';
                del.setAttribute('aria-label', @json(__("O'chirish")));
                del.addEventListener('click', () => { files.splice(i, 1); sync(); });
                cell.append(img, del);
                box.appendChild(cell);
            });
        }

        input.addEventListener('change', () => {
            const picked = Array.from(input.files || []);
            for (const file of picked) {
                if (files.length >= MAX) { alert(@json(__("Ko'pi bilan :max ta rasm yuklash mumkin.", ['max' => $maxImages]))); break; }
                if (file.size > MAX_BYTES) { alert(file.name + ': ' + @json(__('Har bir rasm 5 MB dan oshmasligi kerak.'))); continue; }
                files.push(file);
            }
            sync();
        });

        document.getElementById('cpForm').addEventListener('submit', () => {
            const btn = document.getElementById('cpSubmit');
            btn.disabled = true;
            btn.textContent = @json(__('Yuborilmoqda...'));
        });
    })();
    </script>
</x-student-app-layout>
