# Filament CMS

A small CMS for client websites on **Filament 5**: a page builder for pages, plus fixed fields, lists and settings.

You build the page in Blade and CSS. The client edits its text, photos and lists in the panel, and cannot break the design: they fill in sections, they do not move them.

- **Pages and sections**: each page is a list of sections. A section declares its fields with normal Filament components and the text it shows before anything is saved.
- **Lists**: use a Filament `Repeater` with `maxItems()`. The client adds, removes and reorders items up to the limit.
- **Hide a section**: the client can switch a section off, unless the section says it cannot be hidden.
- **Settings**: business name, logo, WhatsApp numbers, address, opening hours, social links, and Google settings, read anywhere with `cms_settings()`.
- **Versions**: the newest 20 saves of every page are kept. Developers can restore one.
- **Photos**: shrunk in the browser to at most 1920 pixels before upload.

The package never draws the public page and ships no CSS. Each project owns its look.

## Install

Both packages install from GitHub (they are not on Packagist):

```bash
composer config repositories.vortechron-cms vcs https://github.com/vortechron/filament-cms.git
composer config repositories.vortechron-block-editor vcs https://github.com/vortechron/filament-block-editor.git
composer require vortechron/filament-cms:^0.2
php artisan vendor:publish --tag=filament-cms-config
php artisan migrate
php artisan storage:link
php artisan filament:assets
```

Add the plugin to the panel:

```php
use Vortechron\FilamentCms\FilamentCmsPlugin;

$panel->plugin(
    FilamentCmsPlugin::make()->developers(fn (User $user): bool => $user->is_developer),
);
```

## Site pages built in the page builder

The **Halaman** screen lists the client's pages. Each page has a title, an address (`/` for the home page,
`/about`, ...) and its content, built full screen in the page builder from
[`vortechron/filament-block-editor`](https://github.com/vortechron/filament-block-editor) (the "Bina" button).
Saving puts the content live, keeps the newest 20 versions, and developers can restore one from the table.

Show the pages on the site by calling this last in `routes/web.php`:

```php
use Vortechron\FilamentCms\FilamentCms;

FilamentCms::routes();
```

Each page is drawn by your own Blade view, `config('filament-cms.page_view')` (`cms.page` by default). It gets
`$page`, `$content` (the sections as HTML), `$sections`, `$context` and `$isBuilderPreview`. The same view draws
the builder's live preview, so the client edits inside your real header, footer and CSS:

```blade
<head>
    <x-filament-block-editor::styles />
    <link rel="stylesheet" href="/site.css">
</head>
<body>
    @include('partials.header')
    <main>{!! $content !!}</main>
    @include('partials.footer')
</body>
```

Register your own blocks (the parts of your design the client fills in but cannot break) with the block
editor's `BlockRegistry`; see its README. Turn the screen off with `FilamentCmsPlugin::make()->sitePages(false)`.

## A page and its sections

```php
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Vortechron\FilamentCms\CmsSection;

class PricingSection extends CmsSection
{
    public function label(): string
    {
        return 'Pakej';
    }

    public function fields(): array
    {
        return [
            TextInput::make('heading'),
            Repeater::make('packages')
                ->schema([TextInput::make('name')->required(), TextInput::make('price')->prefix('RM')])
                ->maxItems(6)
                ->addActionLabel('Tambah pakej'),
        ];
    }

    public function defaults(): array
    {
        return ['heading' => 'Pakej kami', 'packages' => [['name' => 'Asas', 'price' => '49']]];
    }
}
```

```php
use Vortechron\FilamentCms\CmsPage;

class HomePage extends CmsPage
{
    public static function key(): string { return 'home'; }

    public static function label(): string { return 'Halaman utama'; }

    public function sections(): array
    {
        return [HeroSection::make('hero'), PricingSection::make('pakej')];
    }
}
```

Register it in `config/filament-cms.php`:

```php
'pages' => [App\Cms\HomePage::class],
```

For a link the client fills in, use `CmsUrl::make('url')`: it accepts only http and https (Laravel's plain `url` rule also accepts `data://` and `ftp://`). Print it with `cms_url($value)`, which returns null for anything else.

For a photo, use `CmsImage::make('photo')`. It accepts JPEG, PNG, WebP and GIF (no SVG, which can carry script) and shrinks the photo in the browser. Raise PHP's `upload_max_filesize` above its 2 MB default anyway, for example in `public/.user.ini`.

## Read it in Blade

```blade
@php($page = cms('home'))

@if ($page->visible('pakej'))
    <section class="pakej">
        <h2>{{ $page->section('pakej')->heading }}</h2>
        @foreach ($page->section('pakej')->packages as $package)
            <div class="card">{{ $package['name'] }} <b>RM{{ $package['price'] }}</b></div>
        @endforeach
    </section>
@endif

<img src="{{ $page->section('hero')->image('photo') }}" alt="">
<a href="{{ cms_url(cms_settings()->section('contact')->map_url) }}">Google Maps</a>
<a href="{{ cms_whatsapp_url(cms_settings()->section('contact')->phones[0] ?? null, 'Salam') }}">WhatsApp</a>
```

## Settings

`config('filament-cms.settings')` points at `StandardSettings`. Extend it to add a section or change defaults:

```php
class SiteSettings extends StandardSettings
{
    public function sections(): array
    {
        return [...parent::sections(), DeliverySettings::make('delivery')];
    }
}
```

To give the standard settings this client's starting values, override `defaults()`:

```php
class SiteSettings extends StandardSettings
{
    public function defaults(): array
    {
        return ['business' => ['name' => 'Kedai Contoh'], 'contact' => ['phones' => ['012-345 6789']]];
    }
}
```

`defaults()` works on any page. Its values sit on top of each section's own defaults.

## Tests

```bash
composer test
```
