<x-student-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Fanlar ro\'yxati va baholar') }} {{"({$semester})"}}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 px-4 py-2 bg-green-100 border border-green-400 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 px-4 py-2 bg-red-100 border border-red-400 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 px-4 py-2 bg-red-100 border border-red-400 text-red-700 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">#</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Fan</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Kredit</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Fan turi</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Joriy baho</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Joriy baho(Baza)</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Yakuniy baho</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Umumiy baho</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-center text-gray-500 uppercase">MT</th>
                                <th scope="col" class="px-6 py-3 text-xs font-medium tracking-wider text-left text-gray-500 uppercase">Batafsil</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($subjects as $index => $subject)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{{ $index + 1 }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900 whitespace-nowrap">{{ $subject['name'] }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{{ $subject['credit'] }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{{ $subject['subject_type'] }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{{ $subject['current_exam'] }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{{ number_format(round($subject['average_grade'])) }}/100</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{{ $subject['final_exam'] }}</td>
                                    <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{{ $subject['overall_score'] }}</td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap text-center">
                                        @if($subject['mt'])
                                            @php $mt = $subject['mt']; @endphp

                                            {{-- MT Button with status --}}
                                            @if($mt['grade_locked'])
                                                <button onclick="toggleMtPopover(event, {{ $index }})"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-green-100 text-green-800 border border-green-300 hover:bg-green-200 transition">
                                                    Qabul <span class="ml-1 font-bold">{{ $mt['grade'] }}</span>
                                                </button>
                                            @elseif($mt['can_resubmit'])
                                                <button onclick="toggleMtPopover(event, {{ $index }})"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-orange-100 text-orange-800 border border-orange-300 hover:bg-orange-200 transition animate-pulse">
                                                    Qayta yuklash
                                                </button>
                                            @elseif($mt['submission'] && $mt['grade'] !== null && $mt['grade'] < 60 && $mt['remaining_attempts'] <= 0)
                                                <button onclick="toggleMtPopover(event, {{ $index }})"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-100 text-red-800 border border-red-300 hover:bg-red-200 transition">
                                                    Imkoniyat tugadi
                                                </button>
                                            @elseif($mt['submission'])
                                                <button onclick="toggleMtPopover(event, {{ $index }})"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-blue-100 text-blue-800 border border-blue-300 hover:bg-blue-200 transition">
                                                    Yuklangan
                                                </button>
                                            @elseif($mt['is_overdue'])
                                                <button onclick="toggleMtPopover(event, {{ $index }})"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-100 text-gray-500 border border-gray-300 hover:bg-gray-200 transition">
                                                    Muddat tugagan
                                                </button>
                                            @elseif($mt['days_remaining'] !== null && $mt['days_remaining'] <= 3)
                                                <button onclick="toggleMtPopover(event, {{ $index }})"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-100 text-red-800 border border-red-300 hover:bg-red-200 transition animate-pulse">
                                                    MT yuklash
                                                </button>
                                            @else
                                                <button onclick="toggleMtPopover(event, {{ $index }})"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-semibold rounded-lg bg-amber-100 text-amber-800 border border-amber-300 hover:bg-amber-200 transition">
                                                    MT yuklash
                                                </button>
                                            @endif
                                        @else
                                            <span class="text-xs text-gray-400">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium whitespace-nowrap">
                                        <a href="{{ route('student.subject.grades', $subject['subject_id']) }}" class="text-indigo-600 hover:text-indigo-900">Batafsil</a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- MT Popovers (outside table for proper z-index and overflow handling) --}}
    @foreach($subjects as $index => $subject)
        @if($subject['mt'])
            @php $mt = $subject['mt']; @endphp
            <div id="mt-popover-{{ $index }}" class="hidden fixed z-[9999] w-80 bg-white rounded-xl shadow-2xl border border-gray-200" style="max-height: 80vh; overflow-y: auto;">
                <div class="p-4">
                    {{-- Header --}}
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-gray-200">
                        <h3 class="text-sm font-bold text-gray-800">Mustaqil ta'lim</h3>
                        <button onclick="closeAllMtPopovers()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Deadline info --}}
                    <div class="mb-3 p-2 rounded-lg {{ $mt['is_overdue'] ? 'bg-red-50' : ($mt['days_remaining'] !== null && $mt['days_remaining'] <= 3 ? 'bg-orange-50' : 'bg-blue-50') }}">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-gray-600">Muddat:</span>
                            <span class="text-xs font-bold {{ $mt['is_overdue'] ? 'text-red-600' : 'text-gray-800' }}">
                                {{ $mt['deadline'] }} ({{ $mt['deadline_time'] }} gacha)
                            </span>
                        </div>
                        @if($mt['is_overdue'])
                            <div class="text-xs text-red-600 font-semibold mt-1 text-right">Muddat tugagan</div>
                        @elseif($mt['days_remaining'] !== null)
                            <div class="text-xs {{ $mt['days_remaining'] <= 3 ? 'text-orange-600 font-semibold' : 'text-blue-600' }} mt-1 text-right">
                                Qolgan: {{ $mt['days_remaining'] }} kun
                            </div>
                        @endif
                    </div>

                    {{-- Current submission info --}}
                    @if($mt['submission'])
                        <div class="mb-3 p-2 bg-green-50 rounded-lg">
                            <div class="flex items-center space-x-1 mb-1">
                                <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-xs font-medium text-green-800">Yuklangan fayl</span>
                            </div>
                            <a href="{{ asset('storage/' . $mt['submission']->file_path) }}" target="_blank"
                               class="text-xs text-blue-600 hover:text-blue-800 underline break-all">
                                {{ $mt['submission']->file_original_name }}
                            </a>
                            <div class="text-xs text-gray-500 mt-0.5">
                                {{ $mt['submission']->submitted_at ? $mt['submission']->submitted_at->format('d.m.Y H:i') : '' }}
                            </div>
                        </div>
                    @endif

                    {{-- Grade info --}}
                    @if($mt['grade'] !== null)
                        <div class="mb-3 p-2 rounded-lg {{ $mt['grade'] >= 60 ? 'bg-green-50' : 'bg-red-50' }}">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-gray-600">Baho:</span>
                                <span class="text-sm font-bold {{ $mt['grade'] >= 60 ? 'text-green-700' : 'text-red-700' }}">
                                    {{ $mt['grade'] }}
                                    @if($mt['grade_locked'])
                                        <span class="text-xs font-normal text-green-600">(Qabul qilindi)</span>
                                    @else
                                        <span class="text-xs font-normal text-red-600">(Qoniqarsiz)</span>
                                    @endif
                                </span>
                            </div>
                        </div>
                    @endif

                    {{-- Grade history --}}
                    @if($mt['grade_history']->count() > 0)
                        <div class="mb-3 p-2 bg-gray-50 rounded-lg">
                            <span class="text-xs font-medium text-gray-600">Oldingi baholar:</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach($mt['grade_history'] as $history)
                                    <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full {{ $history->grade >= 60 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $history->grade }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- File upload section --}}
                    @if($mt['grade_locked'])
                        {{-- Locked: no upload --}}
                    @elseif($mt['can_resubmit'])
                        {{-- Resubmit after low grade --}}
                        <div class="mb-3 p-2 bg-orange-50 rounded-lg border border-orange-200">
                            <div class="text-xs text-orange-700 font-medium mb-2">
                                Qayta yuklash ({{ $mt['remaining_attempts'] }} marta qoldi)
                            </div>
                            <form method="POST" action="{{ route('student.independents.submit', $mt['id']) }}"
                                  enctype="multipart/form-data">
                                @csrf
                                <input type="file" name="file" required
                                       accept=".zip,.doc,.docx,.ppt,.pptx,.pdf"
                                       class="w-full text-xs file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-orange-100 file:text-orange-700 hover:file:bg-orange-200 mb-2">
                                <button type="submit"
                                        class="w-full px-3 py-1.5 bg-orange-500 text-white text-xs rounded-lg hover:bg-orange-600 transition font-medium">
                                    Qayta yuklash
                                </button>
                            </form>
                            <p class="text-xs text-gray-400 mt-1">Max 2MB (zip, doc, ppt, pdf)</p>
                        </div>
                    @elseif($mt['submission'] && $mt['grade'] === null && !$mt['is_overdue'])
                        {{-- Already uploaded but not graded yet, can re-upload --}}
                        <div class="mb-3 p-2 bg-blue-50 rounded-lg border border-blue-200">
                            <div class="text-xs text-blue-700 font-medium mb-2">Faylni yangilash</div>
                            <form method="POST" action="{{ route('student.independents.submit', $mt['id']) }}"
                                  enctype="multipart/form-data">
                                @csrf
                                <input type="file" name="file" required
                                       accept=".zip,.doc,.docx,.ppt,.pptx,.pdf"
                                       class="w-full text-xs file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200 mb-2">
                                <button type="submit"
                                        class="w-full px-3 py-1.5 bg-blue-500 text-white text-xs rounded-lg hover:bg-blue-600 transition font-medium">
                                    Yangilash
                                </button>
                            </form>
                            <p class="text-xs text-gray-400 mt-1">Max 2MB (zip, doc, ppt, pdf)</p>
                        </div>
                    @elseif(!$mt['submission'] && !$mt['is_overdue'])
                        {{-- First upload --}}
                        <div class="mb-3 p-2 bg-amber-50 rounded-lg border border-amber-200">
                            <div class="text-xs text-amber-700 font-medium mb-2">Fayl yuklash</div>
                            @if($mt['file_path'])
                                <div class="mb-2">
                                    <a href="{{ asset('storage/' . $mt['file_path']) }}" target="_blank"
                                       class="text-xs text-blue-600 hover:text-blue-800 underline">
                                        Topshiriq faylini ko'rish
                                    </a>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('student.independents.submit', $mt['id']) }}"
                                  enctype="multipart/form-data">
                                @csrf
                                <input type="file" name="file" required
                                       accept=".zip,.doc,.docx,.ppt,.pptx,.pdf"
                                       class="w-full text-xs file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-amber-100 file:text-amber-700 hover:file:bg-amber-200 mb-2">
                                <button type="submit"
                                        class="w-full px-3 py-1.5 bg-amber-500 text-white text-xs rounded-lg hover:bg-amber-600 transition font-medium">
                                    Yuklash
                                </button>
                            </form>
                            <p class="text-xs text-gray-400 mt-1">Max 2MB (zip, doc, ppt, pdf)</p>
                        </div>
                    @elseif($mt['is_overdue'] && !$mt['submission'])
                        <div class="mb-3 p-2 bg-gray-50 rounded-lg">
                            <span class="text-xs text-red-500 font-medium">Muddat tugagan — fayl yuklanmagan</span>
                        </div>
                    @elseif($mt['grade'] !== null && $mt['grade'] < 60 && $mt['remaining_attempts'] <= 0)
                        <div class="mb-3 p-2 bg-gray-50 rounded-lg">
                            <span class="text-xs text-red-500 font-medium">MT topshirig'ini qayta yuklash imkoniyati tugagan</span>
                        </div>
                    @endif

                    {{-- Task file download link --}}
                    @if($mt['file_path'] && ($mt['submission'] || $mt['is_overdue']))
                        <div class="mb-3">
                            <a href="{{ asset('storage/' . $mt['file_path']) }}" target="_blank"
                               class="inline-flex items-center text-xs text-blue-600 hover:text-blue-800">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Topshiriq faylini ko'rish
                            </a>
                        </div>
                    @endif

                    {{-- Reminder text --}}
                    <div class="p-2 bg-yellow-50 rounded-lg border border-yellow-200">
                        <p class="text-xs text-yellow-800 leading-relaxed">
                            MT topshiriq muddati oxirgi darsdan bitta oldingi darsda soat 17.00 gacha yuklanishi shart.
                            Muddatida yuklanmagan MT topshiriqlari ko'rib chiqilmaydi va baholanmaydi.
                            MT dan qoniqarsiz baho olgan yoki baholanmagan talabalar fandan akademik qarzdor hisoblanadi.
                        </p>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    {{-- Overlay for closing popovers --}}
    <div id="mt-popover-overlay" class="hidden fixed inset-0 z-[9998] bg-black bg-opacity-10" onclick="closeAllMtPopovers()"></div>

    <script>
        function toggleMtPopover(event, index) {
            event.stopPropagation();
            const popover = document.getElementById('mt-popover-' + index);
            const overlay = document.getElementById('mt-popover-overlay');
            const isHidden = popover.classList.contains('hidden');

            // Close all first
            closeAllMtPopovers();

            if (isHidden) {
                const rect = event.currentTarget.getBoundingClientRect();
                const popoverWidth = 320;
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;

                // Position below the button, aligned to the right
                let top = rect.bottom + 6;
                let left = rect.right - popoverWidth;

                // Ensure popover stays within viewport horizontally
                if (left < 8) left = 8;
                if (left + popoverWidth > viewportWidth - 8) left = viewportWidth - popoverWidth - 8;

                // If popover would overflow bottom, show above the button
                if (top + 300 > viewportHeight) {
                    top = Math.max(8, rect.top - 300 - 6);
                }

                popover.style.top = top + 'px';
                popover.style.left = left + 'px';
                popover.classList.remove('hidden');
                overlay.classList.remove('hidden');
            }
        }

        function closeAllMtPopovers() {
            document.querySelectorAll('[id^="mt-popover-"]').forEach(function(el) {
                if (el.id !== 'mt-popover-overlay') {
                    el.classList.add('hidden');
                }
            });
            const overlay = document.getElementById('mt-popover-overlay');
            if (overlay) overlay.classList.add('hidden');
        }

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAllMtPopovers();
        });
    </script>
</x-student-app-layout>
