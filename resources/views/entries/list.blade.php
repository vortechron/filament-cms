{{--
    The grid of the list block. Styled by <x-filament-cms::entries-styles />.

    $columns  1 to 4
    $type     the entry type's key
    $cards    the HTML of each card, already escaped
--}}
<div class="cms-entries cms-entries-{{ $columns }}" data-entry-type="{{ $type }}">
    @foreach ($cards as $card)
        <div class="cms-entries-item">{!! $card !!}</div>
    @endforeach
</div>
