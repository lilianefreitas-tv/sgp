@php($editing = isset($client))

<div class="grid gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label for="name" class="sgp-field-label">Nome <span class="text-[#C44B4B]">*</span></label>
        <input id="name" name="name" class="sgp-input" value="{{ old('name', $client->name ?? '') }}" maxlength="180" required>
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <label for="type" class="sgp-field-label">Tipo <span class="text-[#C44B4B]">*</span></label>
        <select id="type" name="type" class="sgp-input" required>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', isset($client) ? $client->type->value : 'unit') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('type')" class="mt-2" />
    </div>

    <div>
        <label for="document" class="sgp-field-label">Documento</label>
        <input id="document" name="document" class="sgp-input" value="{{ old('document', $client->document ?? '') }}" maxlength="30" placeholder="CNPJ, CPF ou identificação institucional">
        <x-input-error :messages="$errors->get('document')" class="mt-2" />
    </div>

    <div>
        <label for="contact_name" class="sgp-field-label">Pessoa de contato</label>
        <input id="contact_name" name="contact_name" class="sgp-input" value="{{ old('contact_name', $client->contact_name ?? '') }}" maxlength="180">
        <x-input-error :messages="$errors->get('contact_name')" class="mt-2" />
    </div>

    <div>
        <label for="email" class="sgp-field-label">E-mail</label>
        <input id="email" name="email" type="email" class="sgp-input" value="{{ old('email', $client->email ?? '') }}" maxlength="180">
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <label for="phone" class="sgp-field-label">Telefone</label>
        <input id="phone" name="phone" class="sgp-input" value="{{ old('phone', $client->phone ?? '') }}" maxlength="30">
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>

    <div>
        <label for="is_active" class="sgp-field-label">Situação</label>
        <select id="is_active" name="is_active" class="sgp-input">
            <option value="1" @selected((string) old('is_active', isset($client) ? (int) $client->is_active : 1) === '1')>Ativo</option>
            <option value="0" @selected((string) old('is_active', isset($client) ? (int) $client->is_active : 1) === '0')>Inativo</option>
        </select>
        <p class="mt-2 text-xs text-[#667680]">Registros inativos permanecem no histórico, mas não poderão ser selecionados em novos projetos.</p>
        <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
    </div>
</div>

<div class="mt-7 flex flex-col-reverse gap-3 border-t border-[#E8EDF0] pt-5 sm:flex-row sm:justify-end">
    <a href="{{ route('clients.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#DCE3E7] px-5 py-3 text-sm font-semibold text-[#24313A] hover:bg-[#F5F7F9]">Cancelar</a>
    <button type="submit" class="sgp-button-primary sm:w-auto">{{ $editing ? 'Salvar alterações' : 'Cadastrar' }}</button>
</div>
