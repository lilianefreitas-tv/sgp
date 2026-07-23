<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Http\Requests\StoreDocumentTemplateRequest;
use App\Models\DocumentTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentTemplateController extends Controller
{
    public function index(): View
    {
        return view('document-templates.index', [
            'templates' => DocumentTemplate::query()->latest('updated_at')->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('document-templates.create', [
            'types' => DocumentType::options(),
        ]);
    }

    public function store(StoreDocumentTemplateRequest $request): RedirectResponse
    {
        DocumentTemplate::create($this->data($request) + [
            'created_by' => $request->user()->id,
        ]);

        return to_route('document-templates.index')->with('success', 'Modelo cadastrado com sucesso.');
    }

    public function edit(DocumentTemplate $documentTemplate): View
    {
        return view('document-templates.edit', [
            'documentTemplate' => $documentTemplate,
            'types' => DocumentType::options(),
        ]);
    }

    public function update(
        StoreDocumentTemplateRequest $request,
        DocumentTemplate $documentTemplate,
    ): RedirectResponse {
        $documentTemplate->update($this->data($request));

        return to_route('document-templates.index')->with('success', 'Modelo atualizado com sucesso.');
    }

    public function deactivate(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $documentTemplate->update(['is_active' => false]);

        return back()->with('success', 'Modelo inativado. Os documentos já gerados foram preservados.');
    }

    public function reactivate(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator(), 403);
        $documentTemplate->update(['is_active' => true]);

        return back()->with('success', 'Modelo reativado com sucesso.');
    }

    /** @return array<string, mixed> */
    private function data(StoreDocumentTemplateRequest $request): array
    {
        return $request->safe()->except('is_active') + [
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
