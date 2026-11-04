{{--
    The "cms-entries" own block (Entries\EntryList). $values are its cleaned
    fields, $block the BlockNote block, $context the RenderContext.
--}}
{!! app(\Vortechron\FilamentCms\Entries\EntryList::class)->render($values, $context, $block) !!}
