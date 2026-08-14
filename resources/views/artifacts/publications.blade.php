<section class="rounded-2xl border border-[#DCE3E7] bg-white p-5 shadow-sm sm:p-6">
    <div>
        <p class="text-xs font-bold uppercase tracking-[.18em] text-[#287EA1]">P04.5 · Engenharia documental adaptativa</p>
        <h2 class="mt-1 text-lg font-bold text-[#24313A]">Publicações documentais</h2>
        <p class="mt-1 text-sm leading-6 text-[#667680]">Escolha a finalidade da saída. O cadastro operacional e o histórico permanecem íntegros.</p>
    </div>

    @if($artifact->workflow_state === \App\Enums\ArtifactWorkflowState::Approved && ! $artifact->archived_at)
        <form method="post" action="{{ route('artifacts.publications.store', $artifact) }}" class="mt-5 rounded-xl border border-[#DCE3E7] bg-[#F8FAFB] p-4">
            @csrf
            <div class="grid gap-4 lg:grid-cols-3">
                <div><label class="sgp-field-label" for="mode">Formato da publicação</label><select class="sgp-input" id="mode" name="mode" required>@foreach($publicationModes as $value => $label)<option value="{{ $value }}" @selected(old('mode', 'individual') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="sgp-field-label" for="audience">Audiência</label><select class="sgp-input" id="audience" name="audience" required>@foreach($publicationAudiences as $value => $label)<option value="{{ $value }}" @selected(old('audience', 'internal') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="sgp-field-label" for="purpose">Finalidade</label><input class="sgp-input" id="purpose" name="purpose" maxlength="255" value="{{ old('purpose') }}" placeholder="Ex.: apresentação ao cliente"></div>
            </div>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div><label class="sgp-field-label" for="reference_revision_id">Revisão de referência</label><select class="sgp-input" id="reference_revision_id" name="reference_revision_id"><option value="">Selecione quando comparar alterações</option>@foreach($artifact->revisions->where('sequence', '<', $artifact->current_revision_sequence)->sortByDesc('sequence') as $revision)<option value="{{ $revision->id }}" @selected((string) old('reference_revision_id') === (string) $revision->id)>Revisão {{ $revision->sequence }} · {{ $revision->recorded_at }}</option>@endforeach</select><p class="mt-1 text-xs text-[#667680]">Necessária somente nos modos incremental e comparativo.</p></div>
                <fieldset><legend class="sgp-field-label">Seções do pacote personalizado</legend><div class="grid gap-2 sm:grid-cols-2">@foreach(array_keys($publicationSections) as $section)<label class="flex items-center gap-2 rounded-lg border border-[#DCE3E7] bg-white px-3 py-2 text-sm text-[#24313A]"><input type="checkbox" name="sections[]" value="{{ $section }}" class="rounded border-[#B8C5CB] text-[#123B4A] focus:ring-[#287EA1]" @checked(in_array($section, old('sections', []), true))>{{ ucfirst(str_replace('_', ' ', $section)) }}</label>@endforeach</div><p class="mt-1 text-xs text-[#667680]">Usadas somente no modo personalizado.</p></fieldset>
            </div>
            <div class="mt-4 flex justify-end"><button class="sgp-button-primary sm:w-auto">Gerar publicação</button></div>
        </form>
    @else
        <div class="mt-5 rounded-xl border border-dashed border-[#CBD5DA] p-5 text-sm text-[#667680]">A revisão vigente precisa estar aprovada para liberar uma nova publicação.</div>
    @endif

    <div class="mt-5 space-y-3">
        @forelse($artifact->publications as $publication)
            <article class="rounded-xl border border-[#DCE3E7] bg-[#F8FAFB] p-4">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><strong class="text-[#24313A]">Publicação {{ $publication->sequence }} · {{ $publication->mode->label() }}</strong><p class="mt-1 text-xs text-[#667680]">{{ $publication->audience->label() }} · Revisão {{ $publication->revision->sequence }} · {{ $publication->publisher->name }}</p>@if($publication->purpose)<p class="mt-1 text-xs text-[#52656F]">Finalidade: {{ $publication->purpose }}</p>@endif</div><span class="rounded-full px-3 py-1 text-xs font-bold {{ $publication->status === \App\Enums\ArtifactPublicationStatus::Published ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">{{ $publication->status->label() }}</span></div>
                <div class="mt-3 flex flex-wrap items-center gap-3"><a class="text-sm font-semibold text-[#1D5D73]" href="{{ route('artifact-publications.download', $publication) }}">Baixar pacote ZIP</a>@if($publication->status === \App\Enums\ArtifactPublicationStatus::Published)<form method="post" action="{{ route('artifact-publications.revoke', $publication) }}" class="flex flex-wrap gap-2">@csrf @method('PATCH')<input name="reason" required maxlength="10000" class="rounded-lg border-[#CBD5DA] text-xs" placeholder="Motivo da revogação"><button class="text-sm font-semibold text-[#C44B4B]">Revogar</button></form>@endif</div>
                <p class="mt-2 break-all text-[11px] text-[#7A8991]">SHA-256: {{ $publication->package_checksum }}</p>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-[#CBD5DA] p-5 text-sm text-[#667680]">Nenhuma publicação gerada.</div>
        @endforelse
    </div>
</section>
