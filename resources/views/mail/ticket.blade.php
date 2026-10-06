{{-- Layout próprio: o padrão do Laravel exibe o nome da plataforma no cabeçalho e no rodapé. --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$summary->portalUrl">
{{ $summary->tenantName }}
</x-mail::header>
</x-slot:header>

# {{ $title }}

Olá, {{ $summary->customerFirstName() }}! {{ $intro }}

<x-mail::panel>
**#{{ $summary->number }} · {{ $summary->subject }}**
@if ($quote)

{{ $quote }}
@endif
</x-mail::panel>

<x-mail::button :url="$summary->ticketUrl" :brand="$summary->brandColor">
{{ $actionText }}
</x-mail::button>

@if ($outro)
{{ $outro }}
@endif

<x-slot:footer>
<x-mail::footer>
{{ $summary->tenantName }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
