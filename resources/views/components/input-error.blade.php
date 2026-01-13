@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'ui-help ui-help--error ui-help-list']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
