{{--
    The "cms-entries" own block (Entries\EntryList). $values are its cleaned
    fields, $context the RenderContext.
--}}
{!! app(\Vortechron\FilamentCms\Entries\EntryList::class)->render($values, $context) !!}
