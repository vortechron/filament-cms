{{--
    An entry's page when its type has no "Reka halaman entri" design yet:
    the title, the first picture, then each field. Styled by
    <x-filament-cms::entries-styles /> with the design kit's variables.

    $entry   the Models\Entry
    $type    its Entries\EntryType, or null
    $values  its dynamic values (entry.*), resolved and checked
--}}
@php
    $fields = collect($type?->getFields() ?? [])->map(fn ($field) => $field->toArray());
    $photo = $type?->firstField('image');
@endphp
<article class="cms-entry">
    <div class="cms-entry-inner">
        <h1 class="cms-entry-title">{{ $entry->title }}</h1>
        @if ($photo !== null && filled($values['entry.'.$photo->name] ?? null))
            <img class="cms-entry-photo" src="{{ $values['entry.'.$photo->name] }}" alt="{{ $entry->title }}" loading="lazy" decoding="async">
        @endif
        @foreach ($fields as $field)
            @php($value = $values['entry.'.$field['name']] ?? null)
            @continue($value === null || $value === '' || ($photo !== null && $field['name'] === $photo->name))
            @if ($field['type'] === 'textarea')
                <p class="cms-entry-text">{!! nl2br(e($value), false) !!}</p>
            @elseif ($field['type'] === 'image')
                <img class="cms-entry-photo" src="{{ $value }}" alt="" loading="lazy" decoding="async">
            @elseif ($field['type'] === 'link')
                <p class="cms-entry-field"><a href="{{ $value }}" rel="noopener">{{ $field['label'] }}</a></p>
            @else
                <p class="cms-entry-field"><span>{{ $field['label'] }}</span> {{ $value }}</p>
            @endif
        @endforeach
    </div>
</article>
