# Filament CMS

A small CMS for client websites on **Filament 5**: a page builder for pages, plus fixed fields, lists and settings.

You build the page in Blade and CSS. The client edits its text, photos and lists in the panel, and cannot break the design: they fill in sections, they do not move them.

- **Site pages** built full screen in the page builder, with a shared **header and footer**, **synced sections**
  (one section many pages show), **saved sections**, the site's **design kit**, and the shop's details as
  **dynamic values** (`site.phone`, `site.whatsapp`, ...).
- **Entries**: post types defined in code (products, events, ...), each with its own screen, a list block, a card
  design and its own pages.
- **Pages and sections**: each page is a list of sections. A section declares its fields with normal Filament components and the text it shows before anything is saved.
- **Lists**: use a Filament `Repeater` with `maxItems()`. The client adds, removes and reorders items up to the limit.
- **Hide a section**: the client can switch a section off, unless the section says it cannot be hidden.
- **Settings**: business name, logo, WhatsApp numbers, address, opening hours, social links, and Google settings, read anywhere with `cms_settings()`.
- **Versions**: the newest 20 saves of every page are kept. Developers can restore one.
- **Photos**: shrunk in the browser to at most 1920 pixels before upload.

The package never draws the public page's design. Each project owns its look; the only CSS it ships is the small,
optional stylesheet of the plain entry card and entry page (see Entries).

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
`$page`, `$content` (the sections as HTML), `$sections`, `$context`, `$isBuilderPreview`, and `$header` and
`$footer` (see below). The same view draws the builder's live preview, so the client edits inside your real
layout and CSS:

```blade
<head>
    <x-filament-block-editor::styles />   {{-- the page CSS and the site's design kit --}}
    <link rel="stylesheet" href="/site.css">
</head>
<body>
    @if ($header !== '') {!! $header !!} @else @include('partials.header') @endif
    <main class="sf-page">{!! $content !!}</main>
    @if ($footer !== '') {!! $footer !!} @else @include('partials.footer') @endif
</body>
```

Register your own blocks (the parts of your design the client fills in but cannot break) with the block
editor's `BlockRegistry`; see its README. Turn the screen off with `FilamentCmsPlugin::make()->sitePages(false)`.

### Documents and the one save path

Everything built in the page builder is a row of `cms_documents` (`Models\Document`): a `kind` (`page`, `header`,
`footer`, `section`, `template`, `kit`, `card`, `single`, `entry`), a `title`, an optional `path`, and `content`, a
page value (`['version' => 2, 'sections' => [...]]`, or the kit for kind `kit`). `Models\SitePage` is the kind
`page`, with the same API as before (`sections`, `published()`, `url()`, `cleanPath()`, `revisionKey()`).

Save every document through `DocumentStore`. It checks the value, upgrades and cleans it, keeps the stored locks
and developer-only data when the person saving is not a developer, saves, and keeps the newest 20 versions:

```php
use Vortechron\FilamentCms\DocumentStore;

$result = app(DocumentStore::class)->save($page, $value, $user);   // ['ok' => true] or ['ok' => false, 'errors' => [...]]
app(DocumentStore::class)->save($page, $value, $user, developer: true);   // say who is a developer yourself
app(DocumentStore::class)->restore($page, $revision, $user);
```

When `developer` is not given, the panel's `developers()` rule decides; outside a panel nobody is a developer.
`SitePageStore` still works and calls the store.

### Header and footer

The Halaman screen has a **Header & footer** area with "Bina" for each. They are built like a page and made on
first use. Your page view gets them as `$header` and `$footer`: the HTML in
`<div data-part="header" class="sf-page">`, or `''` when there is none or nothing in it is shown. Turn them off
with `->parts(false)`.

### Design kit

The builder's "Reka bentuk" panel saves the site's colours, fonts, corners, buttons and forms as one document of
kind `kit`. `<x-filament-block-editor::styles />` prints it, and `cms_kit()` returns it (the stored kit over
`Kit::defaults()`). Set this client's starting values in `config('filament-block-editor.kit')`.

### Synced and saved sections

In the builder a section can be **made synced** (one section many pages show: edit it once, every page changes)
or **saved as a template** (a copy in the add panel). The **Seksyen** screen lists both, with rename, delete and
"Bina" for synced ones. Deleting a synced section names the pages that show it; after that they draw nothing in
its place. Turn it off with `->sections(false)`.

### Shop info in the builder

The header and footer panel of the builder shows the shop's details from Tetapan (business, contact, hours,
social) and saves them like the Tetapan screen does. A settings section of your own shows there when it declares
`builderFields()` (block editor `Field`s) and `builderRules()` (the same rules as its Filament fields):

```php
use Vortechron\FilamentBlockEditor\Blocks\Field;

public function builderFields(): array
{
    return [Field::text('area')->label('Kawasan')];
}

public function builderRules(): array
{
    return ['area' => ['nullable', 'string', 'max:120']];
}
```

### Dynamic values

The builder can put the shop's details into text, links and pictures; they are read when the page is drawn, so a
change in Tetapan shows everywhere at once:

| Kind | Keys |
| --- | --- |
| Text | `site.name`, `site.legal_name`, `site.registration`, `site.phone` (first), `site.phones` (all, "a / b"), `site.email`, `site.address`, `site.hours` (one "days: time" line each), `site.hours_note`, `site.whatsapp_number`, `page.title` |
| Link | `site.whatsapp` (wa.me), `site.phone_link` (tel:), `site.email_link` (mailto:), `site.map`, `site.facebook`, `site.instagram`, `site.tiktok`, `site.threads`, `site.x`, `site.youtube`, `site.shopee`, `site.lazada`, `page.url` |
| Picture | `site.logo` |

Read one in Blade with `app(DynamicValues::class)->resolve('site.phone', $context)`.

### Forms

A form block built in the page builder posts to `POST /_cms/forms/{document}` (route `filament-cms.forms.submit`),
which `FilamentCms::routes()` adds. Call it inside `routes/web.php`, so the `web` middleware (session and CSRF)
applies. A form in the header, the footer or a synced section posts to the page it is shown on.

- **The stored form decides.** The form is read from the saved page, never from the request. Only its fields are
  saved, checked with its rules. The page must be published, or the answer is a 404.
- **Bots are refused quietly**: the hidden `_website` field filled, or the signed time `_t` forged or under 2
  seconds old. More than 5 sends a minute from one address to one form get "Cuba lagi sebentar".
- **Errors and the success message** show inside the form itself. Your page view prints nothing extra.
- **Each submission is saved** in `cms_form_submissions` (`Models\FormSubmission`) with the page path, the
  browser, and a keyed hash of the IP address (never the address).
- **Actions** (set in the builder): **email** (to `@site.email` or an address, sent at once), **webhook** (developers
  only: JSON over https to public addresses only, 5 seconds), then **WhatsApp** (the visitor goes to wa.me with
  the message filled in, `{nama}` style) or **redirect**. A failing action never loses the submission.

The **Borang** screen lists the submissions, newest first, with a badge for new ones. Opening one marks it read.
It can mark spam or unread, delete, and download the filtered rows as CSV. Hide it with `->forms(false)`; forms
still save.

Email needs a real mailer in `.env` (`MAIL_MAILER=smtp` or `ses`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`). With
`log` or `array` no email is sent (a log line says so). The shop email comes from Tetapan, Hubungi. The webhook
needs PHP's curl extension.

## Entries

Entries are like WordPress post types: you define a **type** and its fields in code, the client adds the
entries. Each type gets its own screen under **Kandungan**, a list block for the page builder, and (when it has a
path) a page for every entry.

Register the types in a service provider of the project:

```php
use Illuminate\Support\ServiceProvider;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        app(EntryTypes::class)->register(
            EntryType::make('product')               // key: a-z, 0-9 and dashes, 40 at most
                ->label('Produk')
                ->pluralLabel('Produk')
                ->icon('heroicon-o-cube')
                ->fields([
                    Field::text('price')->label('Harga'),
                    Field::image('photo')->label('Gambar'),
                    Field::textarea('summary')->label('Ringkasan'),
                    Field::select('size')->label('Saiz')->options(['s' => 'Kecil', 'l' => 'Besar']),
                    Field::link('shopee')->label('Beli di Shopee'),
                    Field::list('notes')->label('Nota')->fields([Field::text('line')->label('Baris')])->maxItems(5),
                ])
                ->path('/produk')                    // each entry at /produk/{slug}; ->path(null): no pages
                ->body(true)                         // a body built in the page builder ("Bina")
                ->sort('manual'),                    // newest, title or manual (the client drags them)
        );
    }
}
```

Field names are lowercase letters, digits and underscores (`title`, `url` and `slug` are taken). The path may not
be `/` or start with an address the site uses (`admin`, `livewire`, `storage`, ...). Turn the screens off with
`FilamentCmsPlugin::make()->entries(false)`; "Bina" and the designs below also need the Halaman screen on.

- **The screen** (`/admin/entries/product`): the entries with title, published and updated; a form made from the
  fields (text, textarea, select, toggle, number, link with `CmsUrl`, picture with `CmsImage`, list as a
  repeater); publish, unpublish, delete, drag to reorder for `manual`, and "Bina" for the body. An `Entry` policy
  (`Models\Entry`) decides who may open and change them.
- **Addresses**: `{path}/{slug}`. The slug is made from the title, stays when the title changes, and can be edited;
  a taken one gets `-2`. Changing a type's path in code moves each entry when it is next saved.
- **Reka kad** designs how the list block draws one entry; **Reka halaman entri** designs the top of each entry's
  page. Both open the page builder and offer the entry's values as dynamic values, with the first entry as the
  preview.
- **The list block** "Senarai entri" (`cms-entries`) shows the published entries of a type: how many (1 to 48),
  1 to 4 columns, the order, and a text for when there are none. With no card designed it draws a plain card
  (first picture, title, first textarea, a link to the page). A list inside a card is never drawn.
- **Entry pages** are drawn in your `page_view` with `$page` as the entry: the entry page design, or a plain
  layout of the fields when there is none, then the entry's body. `$page->seo_title` is the title and
  `$page->seo_description` the first textarea.

The plain card and page are styled with the design kit's variables. Print their small stylesheet in the head:

```blade
<x-filament-block-editor::styles />
<x-filament-cms::entries-styles />
```

| Entry value | Kind |
| --- | --- |
| `entry.title`, and `entry.{field}` for text, textarea, number and select fields (the option's label) | Text |
| `entry.url` (its page), and `entry.{field}` for link fields | Link |
| `entry.{field}` for image fields | Picture |

Save entries in code through `EntryStore` (it uses `DocumentStore`, so versions and cleaning work the same):

```php
use Vortechron\FilamentCms\Entries\EntryStore;
use Vortechron\FilamentCms\Models\Entry;

app(EntryStore::class)->save(new Entry(['entry_type' => 'product']), [
    'title' => 'Kopi Kampung',
    'is_published' => true,
    'fields' => ['price' => 'RM 12', 'photo' => 'https://example.com/kopi.jpg'],
], $user);   // ['ok' => true] or ['ok' => false, 'errors' => [...]]
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

`cms_whatsapp_number('012-345 6789')` gives the digits WhatsApp uses (`60123456789`).

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
