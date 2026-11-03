{{--
    The CSS of the built-in entry cards and entry page (resources/css/entries.css),
    inline. Put it in the page <head>, after <x-filament-block-editor::styles />:

    <x-filament-cms::entries-styles />
--}}
<style data-cms-entries>{!! \Vortechron\FilamentCms\Entries\EntryStyles::css() !!}</style>
