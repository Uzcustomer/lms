<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $curriculum->typeLabel() }}: {{ $curriculum->name }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-800 rounded-lg">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-800 rounded-lg">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-4 flex items-center justify-between flex-wrap gap-2">
                <a href="{{ route('admin.oquv-reja.index') }}" class="text-blue-600 hover:underline text-sm">&larr; Barcha o'quv rejalar</a>
                <div class="flex gap-2 flex-wrap">
                    <button type="button" id="toggleEdit"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-amber-500 text-white rounded-lg hover:bg-amber-600">
                        ✎ Tahrirlash rejimi
                    </button>
                    <button type="button" id="addSubjectBtn"
                            class="js-edit-only hidden inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        + Fan qatori qo'shish
                    </button>
                    <a href="{{ route('admin.oquv-reja.export', [$curriculum, 'format' => 'jadval']) }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700">
                        ⬇ Jadval (xlsx)
                    </a>
                    <a href="{{ route('admin.oquv-reja.export', [$curriculum, 'format' => 'setka']) }}"
                       class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-700">
                        ⬇ Setka (xlsx)
                    </a>
                </div>
            </div>

            @if($curriculum->isPlanned())
                <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <div class="flex items-start gap-2 mb-3">
                        <span class="text-lg">⏳</span>
                        <div>
                            <div class="font-semibold text-amber-800 text-sm">Rejalashtirilgan reja — HEMIS'ga bog'lanmagan</div>
                            <div class="text-xs text-amber-700">
                                Bu reja HEMIS o'quv rejasiga bog'lanmagan (masalan yangi 1-kurs yoki yangi yo'nalish).
                                HEMIS'da reja paydo bo'lgach, uni tanlab bog'lang — shundan keyin "Yo'nalish bo'yicha" tabida ko'rinadi.
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.oquv-reja.link-hemis', $curriculum) }}"
                          class="flex flex-wrap items-end gap-2">
                        @csrf
                        <div class="flex-1 min-w-[280px] relative">
                            <label class="block text-xs font-medium text-amber-800 mb-1">HEMIS o'quv rejasini qidiring (kod yoki nom)</label>
                            <input type="text" id="hemisSearch" autocomplete="off"
                                   placeholder="Masalan: 60910200 yoki Davolash..."
                                   class="w-full rounded-md border-amber-300 shadow-sm text-sm focus:ring-amber-500 focus:border-amber-500">
                            <input type="hidden" name="curricula_hemis_id" id="hemisId" required>
                            <div id="hemisResults" class="hidden absolute z-10 mt-1 w-full max-h-60 overflow-y-auto bg-white border border-gray-200 rounded-md shadow-lg text-sm"></div>
                            <div id="hemisChosen" class="hidden mt-1 text-xs text-green-700 font-medium"></div>
                        </div>
                        <button type="submit" id="hemisLinkBtn" disabled
                                class="px-4 py-2 text-sm bg-amber-600 text-white rounded-md hover:bg-amber-700 disabled:opacity-50">
                            🔗 Bog'lash
                        </button>
                    </form>
                </div>

                <script>
                    (function () {
                        const optionsUrl = @json(route('admin.oquv-reja.options'));
                        const search  = document.getElementById('hemisSearch');
                        const results = document.getElementById('hemisResults');
                        const hidden  = document.getElementById('hemisId');
                        const chosen  = document.getElementById('hemisChosen');
                        const linkBtn = document.getElementById('hemisLinkBtn');
                        let timer = null;

                        function esc(s) {
                            return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                        }

                        search.addEventListener('input', function () {
                            hidden.value = ''; linkBtn.disabled = true; chosen.classList.add('hidden');
                            clearTimeout(timer);
                            const q = this.value.trim();
                            if (q.length < 2) { results.classList.add('hidden'); return; }
                            timer = setTimeout(async () => {
                                const p = new URLSearchParams({list: 'all_curricula', q});
                                const items = await (await fetch(optionsUrl + '?' + p, {headers: {'Accept': 'application/json'}})).json();
                                if (!items.length) {
                                    results.innerHTML = '<div class="px-3 py-2 text-gray-400">Topilmadi</div>';
                                } else {
                                    results.innerHTML = items.map(i =>
                                        '<button type="button" class="js-hemis-pick block w-full text-left px-3 py-2 hover:bg-amber-50 border-b border-gray-100" ' +
                                        'data-id="' + i.id + '" data-label="' + esc(i.name) + '">' +
                                        '<div class="font-medium text-gray-800">' + esc(i.name) + '</div>' +
                                        '<div class="text-xs text-gray-500">' + esc((i.specialty_code ? i.specialty_code + ' · ' : '') + (i.education_year_name || '')) + '</div>' +
                                        '</button>'
                                    ).join('');
                                }
                                results.classList.remove('hidden');
                                results.querySelectorAll('.js-hemis-pick').forEach(b => b.addEventListener('click', function () {
                                    hidden.value = this.dataset.id;
                                    search.value = this.dataset.label;
                                    chosen.textContent = '✓ Tanlandi: ' + this.dataset.label + ' (HEMIS id: ' + this.dataset.id + ')';
                                    chosen.classList.remove('hidden');
                                    results.classList.add('hidden');
                                    linkBtn.disabled = false;
                                }));
                            }, 300);
                        });
                        document.addEventListener('click', e => {
                            if (!results.contains(e.target) && e.target !== search) results.classList.add('hidden');
                        });
                    })();
                </script>
            @endif

            @php
                $totalHours  = $curriculum->subjects->sum(fn($s) =>
                    (float) ($s->total_hours ?? ((float)($s->audit_total ?? 0) + (float)($s->independent ?? 0))));
                $totalCredit = $curriculum->subjects->sum(fn($s) => (float) $s->credit);
                $fmt = fn($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ' '), '0'), '.');

                // Vizual guruhlash: ketma-ket bir xil (blok+kod+nom) qatorlarni birlashtirib ko'rsat
                $rows = [];
                $prevKey = null;
                $subjectNum = 0;
                foreach ($curriculum->subjects as $subject) {
                    $key = ($subject->block ?? '') . '|' . ($subject->subject_code ?? '') . '|' . $subject->subject_name;
                    $isContinuation = ($key === $prevKey);
                    if (!$isContinuation) {
                        $subjectNum++;
                    }
                    $rows[] = ['subject' => $subject, 'continuation' => $isContinuation, 'num' => $subjectNum];
                    $prevKey = $key;
                }
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Fan qatorlari</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ $curriculum->subjects->count() }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Fanlar (nom bo'yicha)</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ $curriculum->subjects->unique(fn($s) => mb_strtolower($s->subject_name))->count() }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Jami soat</div>
                    <div class="text-2xl font-semibold text-blue-600">{{ $fmt($totalHours) }}</div>
                </div>
                <div class="bg-white shadow-sm rounded-lg p-4">
                    <div class="text-sm text-gray-500">Jami kredit</div>
                    <div class="text-2xl font-semibold text-green-600">{{ $fmt($totalCredit) }}</div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">#</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Blok</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Fan kodi</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Fan nomi</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Namunaviy rejadagi nomi</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Kurs</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Semestr</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Umumiy soat</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Ma'ruza</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Amaliy</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Lab.</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Seminar</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Mustaqil</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-600">Kredit</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">Izoh</th>
                            <th class="js-edit-only hidden px-3 py-2 text-center font-medium text-gray-600">Amallar</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $item)
                            @php $subject = $item['subject']; $cont = $item['continuation']; @endphp
                            <tr class="{{ $cont ? 'bg-blue-50/40' : 'hover:bg-gray-50' }}
                                       {{ $cont ? 'border-l-2 border-blue-300' : '' }}">
                                {{-- # --}}
                                <td class="px-3 py-2 text-gray-400">
                                    @if(!$cont) {{ $item['num'] }} @else <span class="text-blue-400 pl-1">↳</span> @endif
                                </td>
                                {{-- Blok --}}
                                <td class="px-3 py-2 {{ $cont ? 'text-gray-300' : '' }}">
                                    @if(!$cont) {{ $subject->block }} @endif
                                </td>
                                {{-- Fan kodi --}}
                                <td class="px-3 py-2 {{ $cont ? 'text-gray-300' : '' }}">
                                    @if(!$cont) {{ $subject->subject_code }} @endif
                                </td>
                                {{-- Fan nomi --}}
                                <td class="px-3 py-2 {{ $cont ? 'text-gray-400 italic pl-6' : '' }}">
                                    @if(!$cont)
                                        {{ $subject->subject_name }}
                                    @else
                                        {{ $subject->subject_name }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-amber-700">
                                    @if(!$cont) {{ $subject->reference_name }} @endif
                                </td>
                                <td class="px-3 py-2 text-right">{{ $subject->kurs }}</td>
                                <td class="px-3 py-2 text-right font-medium {{ $cont ? 'text-blue-600' : '' }}">{{ $subject->semester }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($subject->total_hours) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($subject->lecture) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($subject->practice) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($subject->laboratory) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($subject->seminar) }}</td>
                                <td class="px-3 py-2 text-right">{{ $fmt($subject->independent) }}</td>
                                <td class="px-3 py-2 text-right font-medium">{{ $fmt($subject->credit) }}</td>
                                <td class="px-3 py-2 text-gray-500 text-xs">{{ $subject->note }}</td>
                                <td class="js-edit-only hidden px-3 py-2 whitespace-nowrap text-center">
                                    <button type="button"
                                            class="js-edit-subject text-blue-600 hover:text-blue-800 px-1"
                                            title="Tahrirlash"
                                            data-action="{{ route('admin.oquv-reja.subjects.update', [$curriculum, $subject]) }}"
                                            data-block="{{ $subject->block }}"
                                            data-subject_code="{{ $subject->subject_code }}"
                                            data-subject_name="{{ $subject->subject_name }}"
                                            data-reference_name="{{ $subject->reference_name }}"
                                            data-kurs="{{ $subject->kurs }}"
                                            data-semester="{{ $subject->semester }}"
                                            data-total_hours="{{ $subject->total_hours }}"
                                            data-lecture="{{ $subject->lecture }}"
                                            data-practice="{{ $subject->practice }}"
                                            data-laboratory="{{ $subject->laboratory }}"
                                            data-seminar="{{ $subject->seminar }}"
                                            data-independent="{{ $subject->independent }}"
                                            data-credit="{{ $subject->credit }}"
                                            data-note="{{ $subject->note }}">✎</button>
                                    <form method="POST"
                                          action="{{ route('admin.oquv-reja.subjects.destroy', [$curriculum, $subject]) }}"
                                          class="inline"
                                          onsubmit="return confirm('Bu fan qatorini o\'chirishni tasdiqlaysizmi?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 px-1" title="O'chirish">🗑</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    {{-- Fan qatorini qo'shish / tahrirlash modali --}}
    <div id="subjectModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/40">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
                <form id="subjectForm" method="POST" action="">
                    @csrf
                    <input type="hidden" name="_method" id="subjectMethod" value="">

                    <div class="flex items-center justify-between px-6 py-4 border-b">
                        <h3 id="subjectModalTitle" class="text-lg font-semibold text-gray-800">Fan qatori</h3>
                        <button type="button" class="js-close-modal text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                    </div>

                    <div class="px-6 py-4 max-h-[70vh] overflow-y-auto space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fan nomi <span class="text-red-500">*</span></label>
                                <input type="text" name="subject_name" required
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Namunaviy rejadagi nomi</label>
                                <input type="text" name="reference_name"
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Blok</label>
                                <input type="text" name="block"
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fan kodi</label>
                                <input type="text" name="subject_code"
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kurs</label>
                                <input type="text" name="kurs"
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Semestr</label>
                                <input type="text" name="semester"
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Umumiy soat</label>
                                <input type="number" step="0.01" min="0" name="total_hours"
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ma'ruza</label>
                                <input type="number" step="0.01" min="0" name="lecture"
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Amaliy</label>
                                <input type="number" step="0.01" min="0" name="practice"
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Laboratoriya</label>
                                <input type="number" step="0.01" min="0" name="laboratory"
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Seminar</label>
                                <input type="number" step="0.01" min="0" name="seminar"
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mustaqil</label>
                                <input type="number" step="0.01" min="0" name="independent"
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kredit</label>
                                <input type="number" step="0.01" min="0" name="credit"
                                       class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Izoh</label>
                            <input type="text" name="note"
                                   class="w-full border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        <p class="text-xs text-gray-400">
                            Eslatma: «Auditoriya jami» avtomatik hisoblanadi (ma'ruza + amaliy + laboratoriya + seminar).
                        </p>
                    </div>

                    <div class="flex justify-end gap-2 px-6 py-4 border-t bg-gray-50 rounded-b-lg">
                        <button type="button" class="js-close-modal px-4 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700">
                            Bekor qilish
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Saqlash
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function () {
            const modal = document.getElementById('subjectModal');
            const form = document.getElementById('subjectForm');
            const methodInput = document.getElementById('subjectMethod');
            const titleEl = document.getElementById('subjectModalTitle');
            const addUrl = @json(route('admin.oquv-reja.subjects.store', $curriculum));
            const fields = ['block', 'subject_code', 'subject_name', 'reference_name', 'kurs', 'semester',
                'total_hours', 'lecture', 'practice', 'laboratory', 'seminar', 'independent', 'credit', 'note'];

            function setField(name, value) {
                const el = form.elements[name];
                if (el) el.value = (value === undefined || value === null) ? '' : value;
            }

            function openModal(mode, action, data) {
                form.action = action;
                methodInput.value = (mode === 'edit') ? 'PUT' : '';
                titleEl.textContent = (mode === 'edit') ? 'Fan qatorini tahrirlash' : "Yangi fan qatori qo'shish";
                fields.forEach(f => setField(f, data ? data[f] : ''));
                modal.classList.remove('hidden');
            }

            function closeModal() {
                modal.classList.add('hidden');
            }

            // Tahrirlash rejimi: amallar ustuni va "qo'shish" tugmasini ko'rsatish/yashirish
            const toggleBtn = document.getElementById('toggleEdit');
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    const willShow = document.querySelector('.js-edit-only.hidden') !== null;
                    document.querySelectorAll('.js-edit-only').forEach(el => el.classList.toggle('hidden', !willShow));
                    toggleBtn.classList.toggle('bg-amber-500', !willShow);
                    toggleBtn.classList.toggle('bg-amber-700', willShow);
                    toggleBtn.textContent = willShow ? '✓ Tahrirlash yoqilgan' : '✎ Tahrirlash rejimi';
                });
            }

            const addBtn = document.getElementById('addSubjectBtn');
            if (addBtn) addBtn.addEventListener('click', () => openModal('add', addUrl, null));

            document.querySelectorAll('.js-edit-subject').forEach(btn => {
                btn.addEventListener('click', () => openModal('edit', btn.dataset.action, btn.dataset));
            });

            document.querySelectorAll('.js-close-modal').forEach(btn => btn.addEventListener('click', closeModal));
            modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
        })();
    </script>
    @endpush
</x-app-layout>
