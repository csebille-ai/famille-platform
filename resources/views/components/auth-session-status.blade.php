@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'ui-help ui-help--success']) }}>
        {{ $status }}
    </div>
@endif
