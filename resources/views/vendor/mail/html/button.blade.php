{{--
    Botão de e-mail com cor da marca opcional (:brand="#rrggbb").
    O tema usa bordas grossas como "padding" (clientes de e-mail ignoram padding em <a>);
    o estilo inline sobrescreve as mesmas propriedades e prevalece sobre o CSS do tema.
--}}
@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
    'brand' => null,
])
@php
    $brandStyle = \App\Support\BrandColor::isValid($brand)
        ? sprintf(
            'background-color: %1$s; border-top: 8px solid %1$s; border-right: 18px solid %1$s; border-bottom: 8px solid %1$s; border-left: 18px solid %1$s; color: %2$s;',
            $brand,
            \App\Support\BrandColor::readableText($brand),
        )
        : null;
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" @if ($brandStyle) style="{{ $brandStyle }}" @endif>{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
