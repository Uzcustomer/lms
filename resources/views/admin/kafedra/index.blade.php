<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Fakultet va kafedralar tuzilmasi
        </h2>
    </x-slot>

    <div class="py-4">
        <div class="max-w-full mx-auto sm:px-4 lg:px-6">

            <!-- Title -->
            <div class="mb-6 text-center">
                <h1 style="font-size: 1.1rem; font-weight: 700; color: #1e3a5f; text-transform: uppercase; letter-spacing: 0.5px;">
                    Toshkent Tibbiyot Akademiyasi Termiz filiali
                </h1>
                <p style="font-size: 0.9rem; color: #64748b; margin-top: 4px;">Fakultet va kafedralarning tuzilmasi</p>
            </div>

            <!-- 4-column Faculty Grid -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; align-items: start;"
                 id="faculty-grid">
                @foreach($faculties as $index => $faculty)
                <div class="faculty-column" data-faculty-id="{{ $faculty->id }}">
                    <!-- Faculty Header -->
                    <div class="faculty-header" style="
                        background: {{ ['#1e40af', '#047857', '#b45309', '#7c3aed'][$index % 4] }};
                        color: white;
                        padding: 12px 16px;
                        border-radius: 10px 10px 0 0;
                        text-align: center;
                        min-height: 70px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">
                        <h3 style="font-size: 0.8rem; font-weight: 700; line-height: 1.3; text-transform: uppercase;">
                            {{ $faculty->name }}
                        </h3>
                    </div>

                    <!-- Kafedra List -->
                    <div class="kafedra-list" data-faculty-id="{{ $faculty->id }}" style="
                        background: white;
                        border: 2px solid {{ ['#1e40af', '#047857', '#b45309', '#7c3aed'][$index % 4] }}20;
                        border-top: none;
                        border-radius: 0 0 10px 10px;
                        padding: 8px;
                        min-height: 200px;
                    ">
                        @php $facultyKafedras = $kafedras->get($faculty->id, collect()); @endphp
                        @foreach($facultyKafedras as $kIndex => $kafedra)
                        <div class="kafedra-card" data-kafedra-id="{{ $kafedra->id }}" style="
                            background: {{ ['#eff6ff', '#ecfdf5', '#fffbeb', '#f5f3ff'][$index % 4] }};
                            border-left: 3px solid {{ ['#1e40af', '#047857', '#b45309', '#7c3aed'][$index % 4] }};
                            padding: 10px 12px;
                            margin-bottom: 6px;
                            border-radius: 6px;
                            cursor: move;
                            transition: all 0.2s ease;
                            position: relative;
                        ">
                            <div style="display: flex; align-items: flex-start; gap: 8px;">
                                <span style="
                                    display: inline-flex;
                                    align-items: center;
                                    justify-content: center;
                                    min-width: 22px;
                                    height: 22px;
                                    background: {{ ['#1e40af', '#047857', '#b45309', '#7c3aed'][$index % 4] }};
                                    color: white;
                                    font-size: 0.65rem;
                                    font-weight: 700;
                                    border-radius: 50%;
                                    flex-shrink: 0;
                                    margin-top: 1px;
                                ">{{ $kIndex + 1 }}</span>
                                <span style="font-size: 0.75rem; color: #1e293b; line-height: 1.4; font-weight: 500;">
                                    {{ $kafedra->name }}
                                </span>
                            </div>
                            <!-- Transfer button -->
                            <button onclick="openTransferModal({{ $kafedra->id }}, '{{ addslashes($kafedra->name) }}', {{ $faculty->id }})"
                                    title="Boshqa fakultetga o'tkazish"
                                    style="
                                        position: absolute;
                                        top: 6px;
                                        right: 6px;
                                        width: 24px;
                                        height: 24px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        background: white;
                                        border: 1px solid #e2e8f0;
                                        border-radius: 4px;
                                        cursor: pointer;
                                        opacity: 0;
                                        transition: opacity 0.2s;
                                        color: #64748b;
                                    "
                                    class="transfer-btn"
                            >
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                                </svg>
                            </button>
                        </div>
                        @endforeach

                        @if($facultyKafedras->isEmpty())
                        <div style="padding: 20px; text-align: center; color: #94a3b8; font-size: 0.75rem;">
                            Kafedralar mavjud emas
                        </div>
                        @endif
                    </div>

                    <!-- Kafedra count -->
                    <div style="text-align: center; padding: 6px; font-size: 0.7rem; color: #64748b; font-weight: 600;">
                        Jami: {{ $facultyKafedras->count() }} ta kafedra
                    </div>
                </div>
                @endforeach
            </div>

            <!-- Unassigned departments -->
            @if($unassigned->count() > 0)
            <div style="margin-top: 24px; background: white; border-radius: 10px; border: 2px dashed #e2e8f0; padding: 16px;">
                <h3 style="font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 12px;">
                    Tayinlanmagan kafedralar ({{ $unassigned->count() }})
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 8px;">
                    @foreach($unassigned as $dept)
                    <div class="kafedra-card" data-kafedra-id="{{ $dept->id }}" style="
                        background: #f8fafc;
                        border-left: 3px solid #94a3b8;
                        padding: 10px 12px;
                        border-radius: 6px;
                        position: relative;
                    ">
                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                            <span style="font-size: 0.75rem; color: #1e293b; line-height: 1.4; font-weight: 500;">
                                {{ $dept->name }}
                            </span>
                        </div>
                        <button onclick="openTransferModal({{ $dept->id }}, '{{ addslashes($dept->name) }}', null)"
                                title="Fakultetga tayinlash"
                                style="
                                    position: absolute;
                                    top: 6px;
                                    right: 6px;
                                    width: 24px;
                                    height: 24px;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    background: #3b82f6;
                                    border: none;
                                    border-radius: 4px;
                                    cursor: pointer;
                                    color: white;
                                "
                        >
                            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </button>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>

    <!-- Transfer Modal -->
    <div id="transfer-modal" style="
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    ">
        <div style="
            background: white;
            border-radius: 12px;
            padding: 24px;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        ">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="font-size: 1rem; font-weight: 700; color: #1e293b;">Kafedrani o'tkazish</h3>
                <button onclick="closeTransferModal()" style="background: none; border: none; cursor: pointer; color: #94a3b8; font-size: 1.2rem;">&times;</button>
            </div>

            <div style="margin-bottom: 16px;">
                <label style="font-size: 0.75rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 4px;">Kafedra:</label>
                <p id="transfer-kafedra-name" style="font-size: 0.85rem; font-weight: 600; color: #1e293b; padding: 8px 12px; background: #f8fafc; border-radius: 6px;"></p>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-size: 0.75rem; font-weight: 600; color: #64748b; display: block; margin-bottom: 6px;">Yangi fakultet:</label>
                <select id="transfer-faculty-select" style="
                    width: 100%;
                    padding: 10px 12px;
                    border: 2px solid #e2e8f0;
                    border-radius: 8px;
                    font-size: 0.85rem;
                    color: #1e293b;
                    outline: none;
                    transition: border-color 0.2s;
                " onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'">
                    @foreach($faculties as $faculty)
                    <option value="{{ $faculty->id }}">{{ $faculty->name }}</option>
                    @endforeach
                </select>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeTransferModal()" style="
                    padding: 8px 20px;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    background: white;
                    color: #64748b;
                    font-size: 0.8rem;
                    font-weight: 600;
                    cursor: pointer;
                ">Bekor qilish</button>
                <button onclick="submitTransfer()" id="transfer-submit-btn" style="
                    padding: 8px 20px;
                    border: none;
                    border-radius: 8px;
                    background: #3b82f6;
                    color: white;
                    font-size: 0.8rem;
                    font-weight: 600;
                    cursor: pointer;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">O'tkazish</button>
            </div>

            <!-- Status message -->
            <div id="transfer-status" style="display: none; margin-top: 12px; padding: 10px; border-radius: 6px; font-size: 0.8rem;"></div>
        </div>
    </div>

    @push('styles')
    <style>
        .kafedra-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .kafedra-card:hover .transfer-btn {
            opacity: 1 !important;
        }

        /* Responsive: 2 columns on medium screens, 1 on small */
        @media (max-width: 1200px) {
            #faculty-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        @media (max-width: 640px) {
            #faculty-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        let currentKafedraId = null;
        let currentFacultyId = null;

        function openTransferModal(kafedraId, kafedraName, currentFacId) {
            currentKafedraId = kafedraId;
            currentFacultyId = currentFacId;

            document.getElementById('transfer-kafedra-name').textContent = kafedraName;
            document.getElementById('transfer-status').style.display = 'none';

            // Set default selection to a different faculty
            const select = document.getElementById('transfer-faculty-select');
            for (let i = 0; i < select.options.length; i++) {
                if (parseInt(select.options[i].value) !== currentFacId) {
                    select.selectedIndex = i;
                    break;
                }
            }

            const modal = document.getElementById('transfer-modal');
            modal.style.display = 'flex';
        }

        function closeTransferModal() {
            document.getElementById('transfer-modal').style.display = 'none';
            currentKafedraId = null;
            currentFacultyId = null;
        }

        function submitTransfer() {
            const facultyId = document.getElementById('transfer-faculty-select').value;
            const btn = document.getElementById('transfer-submit-btn');
            const status = document.getElementById('transfer-status');

            if (parseInt(facultyId) === currentFacultyId) {
                status.style.display = 'block';
                status.style.background = '#fef3c7';
                status.style.color = '#92400e';
                status.textContent = 'Kafedra allaqachon shu fakultetda!';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Yuklanmoqda...';
            btn.style.opacity = '0.7';

            fetch('{{ route("admin.kafedra.transfer") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    kafedra_id: currentKafedraId,
                    faculty_id: parseInt(facultyId),
                }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    status.style.display = 'block';
                    status.style.background = '#dcfce7';
                    status.style.color = '#166534';
                    status.textContent = data.message;

                    // Reload page after short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 800);
                } else {
                    status.style.display = 'block';
                    status.style.background = '#fee2e2';
                    status.style.color = '#991b1b';
                    status.textContent = data.message || 'Xatolik yuz berdi!';
                    btn.disabled = false;
                    btn.textContent = "O'tkazish";
                    btn.style.opacity = '1';
                }
            })
            .catch(error => {
                status.style.display = 'block';
                status.style.background = '#fee2e2';
                status.style.color = '#991b1b';
                status.textContent = 'Server xatoligi yuz berdi!';
                btn.disabled = false;
                btn.textContent = "O'tkazish";
                btn.style.opacity = '1';
            });
        }

        // Close modal on backdrop click
        document.getElementById('transfer-modal').addEventListener('click', function(e) {
            if (e.target === this) closeTransferModal();
        });

        // Close on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeTransferModal();
        });
    </script>
    @endpush
</x-app-layout>
