# Filament CMS

A small CMS for hand-designed client websites on **Filament 5**.

You build the page in Blade and CSS. The client edits its text, photos and lists in the panel, and cannot break the design: they fill in sections, they do not move them.

- **Pages and sections**: each page is a list of sections. A section declares its fields with normal Filament components and the text it shows before anything is saved.
- **Lists**: use a Filament `Repeater` with `maxItems()`. The client adds, removes and reorders items up to the limit.
- **Hide a section**: the client can switch a section off, unless the section says it cannot be hidden.
- **Settings**: business name, logo, WhatsApp numbers, address, opening hours, social links, and Google settings, read anywhere with `cms_settings()`.
- **Versions**: the newest 20 saves of every page are kept. Developers can restore one.
- **Photos**: shrunk in the browser to at most 1920 pixels before upload.

The package never draws the public page and ships no CSS. Each project owns its look.

## Install

```bash
composer require vortechron/filament-cms
php artisan vendor:publish --tag=filament-cms-config
php artisan migrate
php artisan storage:link
```

Add the plugin to the panel:

```php
use Vortechron\FilamentCms\FilamentCmsPlugin;

$panel->plugin(
    FilamentCmsPlugin::make()->developers(fn (User $user): bool => $user->is_developer),
);
```

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

Any link field a client fills in should allow only web addresses: `TextInput::make('url')->url()->rule('url:http,https')`. Laravel's plain `url` rule also accepts schemes such as `data://` and `ftp://`.

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
        return ['business' => ['name' => 'ROC - Adam Shop'], 'contact' => ['phones' => ['011-2705 1612']]];
    }
}
```

`defaults()` works on any page. Its values sit on top of each section's own defaults.

## Tests

```bash
composer test
```
