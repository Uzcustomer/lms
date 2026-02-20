<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DocumentTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = DocumentTemplate::query()->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $templates = $query->paginate(20)->withQueryString();
        $types = DocumentTemplate::typeLabels();

        return view('admin.document-templates.index', compact('templates', 'types'));
    }

    public function create()
    {
        $types = DocumentTemplate::typeLabels();

        return view('admin.document-templates.create', compact('types'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', array_keys(DocumentTemplate::TYPES)),
            'description' => 'nullable|string|max:1000',
            'file' => 'required|file|mimes:docx|max:10240',
            'is_active' => 'nullable|boolean',
        ], [
            'name.required' => 'Shablon nomini kiriting.',
            'type.required' => 'Shablon turini tanlang.',
            'file.required' => 'Word (.docx) faylni yuklang.',
            'file.mimes' => 'Faqat .docx formatdagi fayllar qabul qilinadi.',
            'file.max' => 'Fayl hajmi 10MB dan oshmasligi kerak.',
        ]);

        $file = $request->file('file');
        $path = $file->store('document-templates', 'public');

        // Agar is_active bo'lsa, shu turdagi boshqa faol shablonlarni o'chirish
        if ($request->boolean('is_active')) {
            DocumentTemplate::where('type', $request->type)->update(['is_active' => false]);
        }

        DocumentTemplate::create([
            'name' => $request->name,
            'type' => $request->type,
            'description' => $request->description,
            'file_path' => $path,
            'file_original_name' => $file->getClientOriginalName(),
            'placeholders' => DocumentTemplate::getPlaceholdersForType($request->type),
            'is_active' => $request->boolean('is_active'),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.document-templates.index')
            ->with('success', 'Shablon muvaffaqiyatli yuklandi.');
    }

    public function show(DocumentTemplate $documentTemplate)
    {
        $placeholders = DocumentTemplate::getPlaceholdersForType($documentTemplate->type);

        return view('admin.document-templates.show', compact('documentTemplate', 'placeholders'));
    }

    public function edit(DocumentTemplate $documentTemplate)
    {
        $types = DocumentTemplate::typeLabels();

        return view('admin.document-templates.edit', compact('documentTemplate', 'types'));
    }

    public function update(Request $request, DocumentTemplate $documentTemplate)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'file' => 'nullable|file|mimes:docx|max:10240',
            'is_active' => 'nullable|boolean',
        ]);

        $data = [
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->hasFile('file')) {
            // Eski faylni o'chirish
            Storage::disk('public')->delete($documentTemplate->file_path);

            $file = $request->file('file');
            $data['file_path'] = $file->store('document-templates', 'public');
            $data['file_original_name'] = $file->getClientOriginalName();
        }

        // Agar is_active bo'lsa, shu turdagi boshqa faol shablonlarni o'chirish
        if ($request->boolean('is_active')) {
            DocumentTemplate::where('type', $documentTemplate->type)
                ->where('id', '!=', $documentTemplate->id)
                ->update(['is_active' => false]);
        }

        $documentTemplate->update($data);

        return redirect()->route('admin.document-templates.index')
            ->with('success', 'Shablon yangilandi.');
    }

    public function destroy(DocumentTemplate $documentTemplate)
    {
        Storage::disk('public')->delete($documentTemplate->file_path);
        $documentTemplate->delete();

        return redirect()->route('admin.document-templates.index')
            ->with('success', 'Shablon o\'chirildi.');
    }

    public function download(DocumentTemplate $documentTemplate)
    {
        $filePath = Storage::disk('public')->path($documentTemplate->file_path);

        if (!file_exists($filePath)) {
            abort(404, 'Shablon fayli topilmadi');
        }

        return response()->download($filePath, $documentTemplate->file_original_name);
    }

    public function activate(DocumentTemplate $documentTemplate)
    {
        // Shu turdagi boshqa shablonlarni deaktivatsiya
        DocumentTemplate::where('type', $documentTemplate->type)->update(['is_active' => false]);

        $documentTemplate->update(['is_active' => true]);

        return back()->with('success', 'Shablon faollashtirildi.');
    }
}
