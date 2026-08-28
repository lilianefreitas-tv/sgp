@php($editing = isset($contract))
@if($errors->any())<div class="mb-5 rounded-xl border border-[#EABBBB] bg-[#FDF1F1] px-4 py-3 text-sm text-[#A23838]">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="grid gap-6 lg:grid-cols-2">
  <section class="space-y-4 rounded-2xl border border-[#DCE3E7] bg-white p-6">
    <h2 class="text-lg font-bold text-[#123F4D]">Identificação do contrato</h2>
    @unless($editing)
      @if(isset($project) && $project)
        <input type="hidden" name="project_id" value="{{ $project->id }}">
        @if($project->initiative_id)<input type="hidden" name="initiative_id" value="{{ $project->initiative_id }}">@endif
        <div class="rounded-xl border border-[#BFD7DF] bg-[#EDF6F8] p-4 text-sm text-[#1D5D73]"><strong>Projeto:</strong> {{ $project->code }} · {{ $project->name }}@if($project->initiative)<br><strong>Iniciativa de origem:</strong> {{ $project->initiative->code }} · {{ $project->initiative->title }}@endif</div>
      @elseif(isset($initiative) && $initiative)
        <input type="hidden" name="initiative_id" value="{{ $initiative->id }}">
        <div class="rounded-xl border border-[#BFE2D9] bg-[#F3FAF8] p-4 text-sm text-[#256C5C]"><strong>Iniciativa:</strong> {{ $initiative->code }} · {{ $initiative->title }}. Se ela for convertida, este contrato será herdado pelo projeto.</div>
      @else
        <div class="rounded-xl border border-[#DCE3E7] bg-[#F8FAFB] p-4">
          <p class="text-sm font-bold text-[#24313A]">Contexto do contrato</p>
          <p class="mt-1 text-xs text-[#667680]">Pode permanecer independente ou nascer já relacionado a uma iniciativa ou projeto existente.</p>
          <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <div><label class="sgp-field-label" for="initiative_id">Iniciativa, se aplicável</label><select id="initiative_id" name="initiative_id" class="sgp-input"><option value="">Sem iniciativa</option>@foreach($initiatives as $initiativeOption)<option value="{{ $initiativeOption->id }}" @selected((string)old('initiative_id')===(string)$initiativeOption->id)>{{ $initiativeOption->code }} · {{ $initiativeOption->title }}</option>@endforeach</select></div>
            <div><label class="sgp-field-label" for="project_id">Projeto existente, se aplicável</label><select id="project_id" name="project_id" class="sgp-input"><option value="">Sem projeto</option>@foreach($projects as $projectOption)<option value="{{ $projectOption->id }}" @selected((string)old('project_id')===(string)$projectOption->id)>{{ $projectOption->code }} · {{ $projectOption->name }}</option>@endforeach</select><p class="mt-1 text-xs text-[#667680]">Ao selecionar um projeto, a iniciativa de origem é derivada automaticamente.</p></div>
          </div>
        </div>
      @endif
    @else
      @if($contract->project || $contract->initiative)<div class="rounded-xl bg-[#F8FAFB] p-4 text-sm text-[#667680]">Vínculo atual: {{ $contract->project?->code ?? 'sem projeto' }}@if($contract->initiative) · {{ $contract->initiative->code }}@endif. O vínculo é controlado fora da edição de conteúdo.</div>@endif
    @endunless
    <div><label class="sgp-field-label">Título *</label><input name="title" class="sgp-input" required value="{{ old('title', $contract->title ?? ($initiative->title ?? '')) }}"></div>
    <div class="grid gap-4 sm:grid-cols-2"><div><label class="sgp-field-label">Tipo</label><select name="contract_kind" class="sgp-input"><option value="public_procurement" @selected(old('contract_kind',$contract->contract_kind ?? 'public_procurement')==='public_procurement')>Contratação pública</option><option value="private" @selected(old('contract_kind',$contract->contract_kind ?? '')==='private')>Contrato privado/PJ</option><option value="internal" @selected(old('contract_kind',$contract->contract_kind ?? '')==='internal')>Instrumento interno</option><option value="other" @selected(old('contract_kind',$contract->contract_kind ?? '')==='other')>Outro</option></select></div><div><label class="sgp-field-label">Situação</label><select name="status" class="sgp-input">@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected(old('status',$contract->status->value ?? 'draft')===$value)>{{ $label }}</option>@endforeach</select></div></div>
    <div><label class="sgp-field-label">Como deseja registrar?</label><select name="entry_mode" class="sgp-input">@foreach($entryModes as $value=>$label)<option value="{{ $value }}" @selected(old('entry_mode',$contract->entry_mode->value ?? 'hybrid')===$value)>{{ $label }}</option>@endforeach</select></div>
    <div><label class="sgp-field-label">Objeto do contrato</label><textarea name="object" rows="4" class="sgp-input">{{ old('object',$contract->object ?? ($initiative->context ?? '')) }}</textarea></div>
    <div class="grid gap-4 sm:grid-cols-2"><div><label class="sgp-field-label">Contratante</label><input name="contracting_party" class="sgp-input" value="{{ old('contracting_party',$contract->contracting_party ?? '') }}"></div><div><label class="sgp-field-label">Contratada</label><input name="contracted_party" class="sgp-input" value="{{ old('contracted_party',$contract->contracted_party ?? '') }}"></div></div>
    <div class="grid gap-4 sm:grid-cols-3"><div><label class="sgp-field-label">Assinatura</label><input type="date" name="signed_at" class="sgp-input" value="{{ old('signed_at',isset($contract)?$contract->signed_at?->format('Y-m-d'):'') }}"></div><div><label class="sgp-field-label">Início</label><input type="date" name="start_date" class="sgp-input" value="{{ old('start_date',isset($contract)?$contract->start_date?->format('Y-m-d'):'') }}"></div><div><label class="sgp-field-label">Término</label><input type="date" name="end_date" class="sgp-input" value="{{ old('end_date',isset($contract)?$contract->end_date?->format('Y-m-d'):'') }}"></div></div>
  </section>
  <section class="space-y-4 rounded-2xl border border-[#DCE3E7] bg-white p-6">
    <div><h2 class="text-lg font-bold text-[#123F4D]">Conteúdo e documentos relacionados</h2><p class="text-sm text-[#667680]">Cole ou redija o texto que fizer sentido. O SGP não obriga cláusulas nem modelo jurídico.</p></div>
    <div><label class="sgp-field-label">Texto do contrato</label><div class="mb-2 flex gap-2"><button type="button" onclick="document.execCommand('bold')" class="rounded border px-3 py-1 font-bold">B</button><button type="button" onclick="document.execCommand('italic')" class="rounded border px-3 py-1 italic">I</button><button type="button" onclick="document.execCommand('insertUnorderedList')" class="rounded border px-3 py-1">Lista</button></div><div id="contract-editor" contenteditable="true" class="min-h-64 rounded-xl border border-[#C9D5DB] p-4">{!! old('content',$contract->content ?? '') !!}</div><textarea id="contract-content" name="content" class="hidden"></textarea></div>
    <div><label class="sgp-field-label">Anexos relacionados</label><input type="file" name="attachments[]" multiple class="sgp-input"><p class="mt-1 text-xs text-[#667680]">Contrato assinado, termo de referência, edital, proposta, ata, ordem de serviço, empenho ou outros arquivos.</p></div>
    <div class="grid gap-4 sm:grid-cols-2"><div><label class="sgp-field-label">Referência externa</label><input name="external_reference" class="sgp-input" value="{{ old('external_reference',$contract->external_reference ?? '') }}"></div><div><label class="sgp-field-label">Valor</label><input type="number" step="0.01" min="0" name="amount" class="sgp-input" value="{{ old('amount',$contract->amount ?? '') }}"></div></div>
    <div><label class="sgp-field-label">Capacidade e observações operacionais</label><textarea name="capacity_notes" rows="3" class="sgp-input">{{ old('capacity_notes',$contract->capacity_notes ?? '') }}</textarea></div>
    <div><label class="sgp-field-label">Motivo desta versão *</label><input name="reason" required class="sgp-input" value="{{ old('reason',$editing?'Atualização contratual.':'Registro inicial.') }}"></div>
  </section>
</div>
<div class="mt-6 flex justify-end gap-3"><a href="{{ route('contracts.index') }}" class="rounded-lg border px-5 py-3">Cancelar</a><button class="sgp-button-primary sm:w-auto">{{ $editing?'Salvar nova versão':'Registrar contrato' }}</button></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const editor = document.getElementById('contract-editor');
  const content = document.getElementById('contract-content');
  const form = editor?.closest('form');
  form?.addEventListener('submit', () => { content.value = editor.innerHTML; });
});
</script>
