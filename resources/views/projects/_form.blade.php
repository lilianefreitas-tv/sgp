@php($editing = isset($project))

<div class="space-y-7">
    <section>
        <h2 class="text-base font-bold text-[#24313A]">Identificação e responsabilidade</h2>
        <p class="mt-1 text-sm text-[#667680]">Informações centrais do projeto e de quem responde por sua condução.</p>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            @unless($editing)
                <div class="sm:col-span-2">
                    <label for="origin_type" class="sgp-field-label">Como este projeto chegou ao SGP? <span class="text-[#C44B4B]">*</span></label>
                    <select id="origin_type" name="origin_type" class="sgp-input" required>
                        @foreach ($originTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('origin_type', 'direct') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="mt-3 rounded-xl border border-[#BFD7DF] bg-[#F4F9FA] px-4 py-3 text-sm text-[#1D5D73]">
                        <strong>Projeto incorporado:</strong> use quando o trabalho já existia antes do cadastro e os contratos, TAP, visão ou demais documentos foram produzidos fora do SGP.
                    </div>
                    <x-input-error :messages="$errors->get('origin_type')" class="mt-2" />
                </div>
            @endunless
            <div class="sm:col-span-2">
                <label for="name" class="sgp-field-label">Nome do projeto <span class="text-[#C44B4B]">*</span></label>
                <input id="name" name="name" class="sgp-input" value="{{ old('name', $project->name ?? '') }}" maxlength="200" required>
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label for="client_id" class="sgp-field-label">Cliente ou unidade demandante</label>
                <select id="client_id" name="client_id" class="sgp-input">
                    <option value="">Sem demandante vinculado</option>
                    @foreach ($clients as $clientOption)
                        <option value="{{ $clientOption->id }}" @selected((string) old('client_id', $project->client_id ?? '') === (string) $clientOption->id)>
                            {{ $clientOption->name }}{{ $clientOption->is_active ? '' : ' (inativo)' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-[#667680]">Opcional. Projetos internos podem ser cadastrados sem demandante.</p>
                <x-input-error :messages="$errors->get('client_id')" class="mt-2" />
            </div>

            <div>
                <label for="manager_id" class="sgp-field-label">Responsável principal <span class="text-[#C44B4B]">*</span></label>
                <select id="manager_id" name="manager_id" class="sgp-input" required>
                    <option value="">Selecione</option>
                    @foreach ($users as $userOption)
                        <option value="{{ $userOption->id }}" @selected((string) old('manager_id', $project->manager_id ?? '') === (string) $userOption->id)>
                            {{ $userOption->name }}{{ $userOption->is_active ? '' : ' (inativo)' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-2 text-xs text-[#667680]">O responsável será incluído automaticamente na equipe como Gerente de Projetos.</p>
                <x-input-error :messages="$errors->get('manager_id')" class="mt-2" />
            </div>
        </div>
    </section>

    <section class="border-t border-[#E8EDF0] pt-7">
        <h2 class="text-base font-bold text-[#24313A]">Contexto do projeto</h2>
        <p class="mt-1 text-sm text-[#667680]">Dados usados futuramente na geração do Documento de Visão.</p>

        <div class="mt-5 grid gap-5">
            <div>
                <label for="objective" class="sgp-field-label">Objetivo <span class="text-[#C44B4B]">*</span></label>
                <textarea id="objective" name="objective" rows="3" class="sgp-input" required>{{ old('objective', $project->objective ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('objective')" class="mt-2" />
            </div>
            <div>
                <label for="description" class="sgp-field-label">Descrição</label>
                <textarea id="description" name="description" rows="4" class="sgp-input">{{ old('description', $project->description ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>
            <div>
                <label for="justification" class="sgp-field-label">Justificativa</label>
                <textarea id="justification" name="justification" rows="3" class="sgp-input">{{ old('justification', $project->justification ?? '') }}</textarea>
                <x-input-error :messages="$errors->get('justification')" class="mt-2" />
            </div>
        </div>
    </section>

    <section class="border-t border-[#E8EDF0] pt-7">
        <h2 class="text-base font-bold text-[#24313A]">Configuração adaptativa</h2>
        <p class="mt-1 text-sm text-[#667680]">As quatro dimensões são independentes e não ativam nem removem módulos automaticamente.</p>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div>
                <label for="execution_nature" class="sgp-field-label">Natureza da execução <span class="text-[#C44B4B]">*</span></label>
                <select id="execution_nature" name="execution_nature" class="sgp-input" required>
                    @foreach ($executionNatures as $value => $label)
                        <option value="{{ $value }}" @selected(old('execution_nature', isset($project) ? $project->execution_nature->value : 'internal') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="mt-2 space-y-1 text-xs text-[#667680]">
                    @foreach ($executionNatures as $value => $label)
                        <p><strong>{{ $label }}:</strong> {{ \App\Enums\ExecutionNature::from($value)->description() }}</p>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('execution_nature')" class="mt-2" />
            </div>
            <div>
                <label for="financial_management_mode" class="sgp-field-label">Tratamento financeiro <span class="text-[#C44B4B]">*</span></label>
                <select id="financial_management_mode" name="financial_management_mode" class="sgp-input" required>
                    @foreach ($financialModes as $value => $label)
                        <option value="{{ $value }}" @selected(old('financial_management_mode', isset($project) ? $project->financial_management_mode->value : 'not_applicable') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <details class="mt-2 text-xs text-[#667680]">
                    <summary class="cursor-pointer font-semibold text-[#287EA1]">Entenda as modalidades</summary>
                    <div class="mt-2 space-y-1">
                        @foreach ($financialModes as $value => $label)
                            <p><strong>{{ $label }}:</strong> {{ \App\Enums\FinancialManagementMode::from($value)->description() }}</p>
                        @endforeach
                    </div>
                </details>
                <x-input-error :messages="$errors->get('financial_management_mode')" class="mt-2" />
            </div>
            <div>
                <label for="management_level" class="sgp-field-label">Nível de gestão <span class="text-[#C44B4B]">*</span></label>
                <select id="management_level" name="management_level" class="sgp-input" required>
                    @foreach ($levels as $value => $label)
                        <option value="{{ $value }}" @selected(old('management_level', isset($project) ? $project->management_level->value : 'essential') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="mt-2 space-y-1 text-xs text-[#667680]">
                    @foreach ($levels as $value => $label)
                        <p><strong>{{ $label }}:</strong> {{ \App\Enums\ManagementLevel::from($value)->description() }}</p>
                    @endforeach
                </div>
                <p class="mt-2 rounded-lg bg-[#EDF6F8] px-3 py-2 text-xs text-[#1D5D73]">Alterar o nível preserva requisitos, tarefas, documentos e histórico.</p>
                <x-input-error :messages="$errors->get('management_level')" class="mt-2" />
            </div>
            <div>
                <label for="methodology" class="sgp-field-label">Metodologia <span class="text-[#C44B4B]">*</span></label>
                <select id="methodology" name="methodology" class="sgp-input" required>
                    @foreach ($methodologies as $value => $label)
                        <option value="{{ $value }}" @selected(old('methodology', $project->methodology?->value ?? 'kanban') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="mt-2 space-y-1 text-xs text-[#667680]">
                    @foreach ($methodologies as $value => $label)
                        <p><strong>{{ $label }}:</strong> {{ \App\Enums\ProjectMethodology::from($value)->description() }}</p>
                    @endforeach
                </div>
                <x-input-error :messages="$errors->get('methodology')" class="mt-2" />
            </div>
            @if (isset($project))
                <div class="sm:col-span-2">
                    <label for="configuration_justification" class="sgp-field-label">Justificativa da alteração dimensional</label>
                    <textarea id="configuration_justification" name="configuration_justification" rows="2" class="sgp-input">{{ old('configuration_justification') }}</textarea>
                    <p class="mt-2 text-xs text-[#667680]">Obrigatória somente quando uma dimensão adaptativa for alterada.</p>
                    <x-input-error :messages="$errors->get('configuration_justification')" class="mt-2" />
                </div>
            @endif
            <div>
                <label for="status" class="sgp-field-label">Status <span class="text-[#C44B4B]">*</span></label>
                <select id="status" name="status" class="sgp-input" required>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', isset($project) ? $project->status->value : 'planning') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>
            <div>
                <label for="start_date" class="sgp-field-label">Data de início</label>
                <input id="start_date" name="start_date" type="date" class="sgp-input" value="{{ old('start_date', isset($project) ? $project->start_date?->format('Y-m-d') : '') }}">
                <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
            </div>
            <div>
                <label for="expected_end_date" class="sgp-field-label">Previsão de entrega</label>
                <input id="expected_end_date" name="expected_end_date" type="date" class="sgp-input" value="{{ old('expected_end_date', isset($project) ? $project->expected_end_date?->format('Y-m-d') : '') }}">
                <x-input-error :messages="$errors->get('expected_end_date')" class="mt-2" />
            </div>
            <div>
                <label for="end_date" class="sgp-field-label">Data de encerramento</label>
                <input id="end_date" name="end_date" type="date" class="sgp-input" value="{{ old('end_date', isset($project) ? $project->end_date?->format('Y-m-d') : '') }}">
                <p class="mt-2 text-xs text-[#667680]">Se o projeto for concluído ou cancelado sem data, o sistema registrará a data atual.</p>
                <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
            </div>
            <div>
                <label for="is_active" class="sgp-field-label">Atividade do registro</label>
                <select id="is_active" name="is_active" class="sgp-input">
                    <option value="1" @selected((string) old('is_active', isset($project) ? (int) $project->is_active : 1) === '1')>Ativo</option>
                    <option value="0" @selected((string) old('is_active', isset($project) ? (int) $project->is_active : 1) === '0')>Inativo</option>
                </select>
                <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
            </div>
        </div>
    </section>
</div>

<div class="mt-7 flex flex-col-reverse gap-3 border-t border-[#E8EDF0] pt-5 sm:flex-row sm:justify-end">
    <a href="{{ $editing ? route('projects.show', $project) : route('projects.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#DCE3E7] px-5 py-3 text-sm font-semibold text-[#24313A] hover:bg-[#F5F7F9]">Cancelar</a>
    <button type="submit" class="sgp-button-primary sm:w-auto">{{ $editing ? 'Salvar alterações' : 'Cadastrar projeto' }}</button>
</div>
