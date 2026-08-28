<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 34px 42px 44px; }
        body { font-family: DejaVu Sans, sans-serif; color: #24313a; font-size: 10.5px; line-height: 1.5; }
        h1 { color: #134559; font-size: 24px; border-bottom: 3px solid #45d6b5; padding-bottom: 10px; margin: 0 0 16px; }
        h2 { color: #1d5d73; font-size: 16px; margin: 22px 0 8px; }
        h3 { color: #1d5d73; font-size: 13px; margin: 17px 0 7px; border-bottom: 1px solid #dbe4e8; padding-bottom: 4px; }
        h4, h5 { color: #315d6b; font-size: 11px; margin: 12px 0 5px; }
        .meta { background: #f1f5f7; padding: 12px; border-radius: 6px; }
        .publication-meta { margin: 12px 0 4px; padding: 8px 10px; background: #f8fafb; border-left: 3px solid #45d6b5; }
        .document-node { page-break-inside: avoid; }
        .document-node-level-0 { margin-top: 4px; }
        .document-node-level-1 { margin-left: 10px; }
        .document-node-level-2, .document-node-level-3 { margin-left: 18px; }
        .document-field { padding: 5px 7px; border-bottom: 1px solid #edf1f3; }
        .document-label { display: inline-block; width: 31%; color: #49606b; font-weight: bold; vertical-align: top; }
        .document-value { display: inline-block; width: 66%; white-space: pre-wrap; }
        .footer { margin-top: 30px; border-top: 1px solid #ccd6dc; padding-top: 10px; font-size: 8.5px; color: #667680; }
    </style>
</head>
<body>
    <h1>{{ $artifact->title }}</h1>
    <div class="meta">
        <strong>{{ $artifact->code }}</strong> · {{ $artifact->type->label() }} · Revisão {{ $round->revision->sequence }}<br>
        Contexto: {{ data_get($manifest, 'context.code') }} · {{ data_get($manifest, 'context.name') }}
    </div>

    @if ($artifact->description)
        <h2>Descrição</h2>
        <p>{{ $artifact->description }}</p>
    @endif

    <div class="publication-meta">
        <strong>Formato:</strong> {{ \App\Support\ArtifactPublicationPresenter::value(data_get($manifest, 'publication.mode')) }} ·
        <strong>Audiência:</strong> {{ \App\Support\ArtifactPublicationPresenter::value(data_get($manifest, 'publication.audience')) }}
        @if (data_get($manifest, 'publication.purpose'))
            <br><strong>Finalidade:</strong> {{ data_get($manifest, 'publication.purpose') }}
        @endif
    </div>

    @include('artifacts.partials.publication-nodes', [
        'nodes' => \App\Support\ArtifactPublicationPresenter::sections($publicationContent),
        'level' => 0,
    ])

    <div class="footer">
        SGP · Documento para leitura humana · Código de verificação da revisão: {{ $round->revision->checksum }}<br>
        O manifesto JSON técnico permanece disponível exclusivamente dentro do pacote verificável.
    </div>
</body>
</html>
