@props(['url'])

@php
    // A URL pública evita dependência do APP_URL local e mantém o template
    // compatível com as notificações Markdown do Laravel.
    $logoUrl = 'https://sgp.dev.br/images/sgp-logo.png';
@endphp

<tr>
<td class="header">
    <table role="presentation" class="brand-header" width="600" cellpadding="0" cellspacing="0" border="0" align="center">
        <tr>
            <td class="brand-symbol-cell" width="72">
                <a href="{{ $url }}" class="brand-link" aria-label="PRISMA SGP">
                    <img src="{{ $logoUrl }}" class="brand-symbol" width="48" height="48" alt="Símbolo do PRISMA SGP">
                </a>
            </td>
            <td class="brand-copy-cell">
                <a href="{{ $url }}" class="brand-link">
                    <span class="brand-name">PRISMA <span class="brand-name-accent">SGP</span></span>
                    <span class="brand-description">Sistema de Gestão de Projetos de Software</span>
                </a>
            </td>
        </tr>
    </table>
</td>
</tr>
