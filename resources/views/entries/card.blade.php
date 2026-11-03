{{--
    The built-in card of the list block, when the type has no "Reka kad"
    design: the first picture, the title, the first textarea, and a link to
    the entry's page when it has one.

    $entry   the Models\Entry
    $type    its Entries\EntryType
    $values  its dynamic values (entry.*), resolved and checked
--}}
@php
    $photo = $type->firstField('image');
    $photo = $photo === null ? null : ($values['entry.'.$photo->name] ?? null);
    $text = $type->firstField('textarea');
    $text = $text === null ? null : ($values['entry.'.$text->name] ?? null);
    $url = $values['entry.url'] ?? null;
@endphp
<article class="cms-entry-card">
    @if (filled($photo))
        <img class="cms-entry-card-photo" src="{{ $photo }}" alt="{{ $entry->title }}" loading="lazy" decoding="async">
    @endif
    <div class="cms-entry-card-body">
        <h3 class="cms-entry-card-title">{{ $entry->title }}</h3>
        @if (filled($text))
            <p class="cms-entry-card-text">{{ \Illuminate\Support\Str::limit($text, 220) }}</p>
        @endif
        @if (filled($url))
            <a class="cms-entry-card-link" href="{{ $url }}">{{ __('filament-cms::entries.view') }}</a>
        @endif
    </div>
</article>
