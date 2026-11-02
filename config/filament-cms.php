<?php

use Vortechron\FilamentCms\Settings\StandardSettings;

return [

    /*
    | The pages the client can edit, as CmsPage classes. Each one becomes an
    | item under "Kandungan" in the panel and is read with cms('<key>').
    */
    'pages' => [],

    /*
    | The site-wide settings (name, phone, address, hours...). Read them with
    | cms_settings(). Extend StandardSettings to add or change sections.
    */
    'settings' => StandardSettings::class,

    /*
    | The project's Blade view that draws a site page. It gets $page, $content
    | (the sections as HTML), $sections, $context and $isBuilderPreview.
    */
    'page_view' => 'cms.page',

    /*
    | Where uploaded photos are stored. It must be a public disk.
    */
    'disk' => 'public',

    /*
    | How many saved versions of each page are kept for restoring.
    */
    'revisions' => 20,

];
