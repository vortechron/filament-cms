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
    | (the sections as HTML), $sections, $context, $isBuilderPreview, and
    | $header and $footer (their HTML, or '' when there is none).
    */
    'page_view' => 'cms.page',

    /*
    | The most pages the client may have, from the package they bought (for
    | example 5 for Website Starter). Every page counts, published or not;
    | the header, footer and sections do not. Empty means no limit.
    */
    'max_pages' => env('CMS_MAX_PAGES'),

    /*
    | Where uploaded photos are stored. It must be a public disk.
    */
    'disk' => 'public',

    /*
    | How many saved versions of each page are kept for restoring.
    */
    'revisions' => 20,

    /*
    | The site's MCP server (FilamentCms::mcp() in routes/web.php), so an AI
    | agent can read and edit the content. `token` is the secret the agent
    | sends as "Authorization: Bearer <token>"; with no token the address
    | answers 404. `user` is the email of the user its saved versions are
    | kept under (null keeps them without a user).
    */
    'mcp' => [
        'token' => env('CMS_MCP_TOKEN'),
        'user' => env('CMS_MCP_USER'),
    ],

];
