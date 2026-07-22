@if($canUploadFiles)
<div class="mt-6 p-4 rounded-lg" style="background: linear-gradient(135deg, #fef3c7, #fffbeb); border: 1px solid #fbbf24;">
    <h4 class="font-semibold text-base mb-3 border-b pb-2" style="color: #92400e;">Fayllar</h4>

    <form action="{{ route('admin.students.files.upload', $student) }}" method="POST" enctype="multipart/form-data" class="mb-4">
        @csrf
        <div class="flex flex-col gap-3">
            <div>
                <label style="font-size:12px; font-weight:600; color:#92400e; display:block; margin-bottom:4px;">Fayl nomi</label>
                <input type="text" name="file_name" required placeholder="Masalan: Diplom nusxasi"
                       style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; box-sizing:border-box;">
            </div>
            <div>
                <label style="font-size:12px; font-weight:600; color:#92400e; display:block; margin-bottom:4px;">Faylni tanlang</label>
                <input type="file" name="file" required
                       style="width:100%; padding:6px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; background:#fff; box-sizing:border-box;">
                <p style="font-size:11px; color:#9ca3af; margin-top:2px;">Maksimum hajmi: 10 MB</p>
            </div>
            <div>
                <button type="submit"
                        style="padding:8px 20px; border:none; border-radius:8px; background:linear-gradient(135deg, #f59e0b, #d97706); color:#fff; font-size:13px; font-weight:700; cursor:pointer; transition:all 0.2s;"
                        onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                    Yuklash
                </button>
            </div>
        </div>
    </form>

    @if($studentFiles->count() > 0)
    <div style="border-top:1px solid #fbbf24; padding-top:12px;">
        <p style="font-size:12px; font-weight:700; color:#92400e; margin-bottom:8px;">Yuklangan fayllar ({{ $studentFiles->count() }})</p>
        <div class="space-y-2">
            @foreach($studentFiles as $sFile)
            <div class="flex items-center justify-between p-3 rounded-lg" style="background:#fff; border:1px solid #e5e7eb;">
                <div class="flex-1 min-w-0">
                    <p style="font-size:13px; font-weight:600; color:#1f2937; margin:0;">{{ $sFile->name }}</p>
                    <p style="font-size:11px; color:#9ca3af; margin:2px 0 0;">
                        {{ $sFile->original_name }} &middot;
                        {{ number_format($sFile->size / 1024, 1) }} KB &middot;
                        {{ $sFile->created_at->format('d.m.Y H:i') }}
                    </p>
                </div>
                <div class="flex items-center gap-2 ml-3">
                    <a href="{{ route('admin.students.files.download', [$student, $sFile]) }}"
                       style="padding:4px 10px; font-size:11px; font-weight:600; color:#fff; background:#3b82f6; border-radius:6px; text-decoration:none; transition:all 0.15s;"
                       onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                        Yuklab olish
                    </a>
                    <form action="{{ route('admin.students.files.delete', [$student, $sFile]) }}" method="POST"
                          onsubmit="return confirm('{{ addslashes($sFile->name) }} faylini o\'chirmoqchimisiz?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                style="padding:4px 10px; font-size:11px; font-weight:600; color:#fff; background:#ef4444; border:none; border-radius:6px; cursor:pointer; transition:all 0.15s;"
                                onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                            O'chirish
                        </button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @else
    <p style="font-size:13px; color:#9ca3af; text-align:center; padding:8px 0;">Hozircha fayllar yuklanmagan</p>
    @endif
</div>
@endif
