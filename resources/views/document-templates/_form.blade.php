@php
    $editing = isset($documentTemplate);
@endphp
<div class="grid gap-6">
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <label for="name" class="sgp-field-label">Nome do modelo *</label>
            <input id="name" name="name" class="sgp-input" required maxlength="180" value="{{ old('name', $documentTemplate->name ?? '') }}" placeholder="Ex.: Documento de Visão Institucional">
        </div>
        <div>
            <label for="version" class="sgp-field-label">Versão do modelo *</label>
            <input id="version" name="version" type="number" min="1" max="999" class="sgp-input" required value="{{ old('version', $documentTemplate->version ?? 1) }}">
        </div>
    </div>
    <div>
        <label for="type" class="sgp-field-label">Tipo de artefato *</label>
        <select id="type" name="type" class="sgp-input" required>
            <option value="">Selecione</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', isset($documentTemplate) ? $documentTemplate->type->value : '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <p class="mt-2 text-xs text-[#667680]">O tipo define quais dados e seções serão usados na geração.</p>
    </div>
    <div>
        <label for="description" class="sgp-field-label">Descrição</label>
        <textarea id="description" name="description" rows="4" class="sgp-input" maxlength="2000" placeholder="Explique quando este modelo deve ser utilizado.">{{ old('description', $documentTemplate->description ?? '') }}</textarea>
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
        <div>
            <label for="header_text" class="sgp-field-label">Texto do cabeçalho</label>
            <input id="header_text" name="header_text" class="sgp-input" maxlength="180" value="{{ old('header_text', $documentTemplate->header_text ?? 'Sistema de Gestão de Projetos de Software') }}">
        </div>
        <div>
            <label for="footer_text" class="sgp-field-label">Texto do rodapé</label>
            <input id="footer_text" name="footer_text" class="sgp-input" maxlength="180" value="{{ old('footer_text', $documentTemplate->footer_text ?? 'Documento gerado automaticamente pelo SGP') }}">
        </div>
    </div>
    <label class="flex items-center gap-3 rounded-xl border border-[#DCE3E7] bg-[#F8FAFB] px-4 py-3 text-sm font-semibold text-[#24313A]">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-[#B8C5CB] text-[#123B4A] focus:ring-[#287EA1]" @checked(old('is_active', $documentTemplate->is_active ?? true))>
        Modelo ativo e disponível para geração
    </label>
</div>
