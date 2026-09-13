@props(['alt' => 'Símbolo facetado do PRISMA SGP'])

<img
    src="{{ asset('images/prisma-symbol.png') }}"
    alt="{{ $alt }}"
    {{ $attributes->merge(['class' => 'object-contain']) }}
>
