@php
    $appUrl = (string) config('app.url');
@endphp

<tr>
<td>
    <table role="presentation" class="footer" align="center" width="600" cellpadding="0" cellspacing="0">
        <tr>
            <td class="content-cell" align="center">
                <p class="brand-tagline">Projetos organizados. Decisões rastreáveis.</p>
                <p class="brand-footer-copy">
                    PRISMA SGP • Sistema de Gestão de Projetos de Software<br>
                    © {{ date('Y') }} PRISMA SGP. Todos os direitos reservados.
                </p>
                <p class="brand-footer-link"><a href="{{ $appUrl }}">Acessar o PRISMA SGP</a></p>
            </td>
        </tr>
    </table>
</td>
</tr>
