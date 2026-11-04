# Filament CMS

Vortechron's CMS package: the client's panel at `/admin` for every client site we promote from a demo
(`~/Projects/vortechron-demos/docs/agents/promote-demo.md`). Pages, header and footer parts, synced sections,
Borang (form inbox), Tetapan (shop info), entries, the site's MCP server, and the page limit. The page builder
itself lives in `vortechron/filament-block-editor` (`~/Projects/filament-block-editor`). What each feature does
and how to use it: `README.md`. Why the design is shaped this way: `docs/v2-deviations.md`.

## Who uses it

- `vortechron/adamshop` (ROC - Adam Shop, `https://adamshopmy.com`, Cloud site 40, `~/Projects/adamshop`).
- Clients install it from GitHub tags (not Packagist), so a change reaches a client only through a release.

## Release a change

1. Tests and format pass: `php84 vendor/bin/phpunit` and `php84 vendor/bin/pint <changed files>`.
2. Update the README for any new option, and the install line when the minor version moves.
3. Commit, tag `vX.Y.Z` (a new feature moves the minor: 0.4 to 0.5), push `main` and the tag.
4. In each client: `composer require vortechron/filament-cms:^X.Y` with no `-W` (it would also pull new Laravel
   and Filament versions), run its tests, push. A push to the client's `main` deploys it.

## Rules

- Panel words are in Bahasa Melayu (`resources/lang/ms`), with English in `resources/lang/en`.
- The page limit (`max_pages`, `PageLimit`) defaults to no limit here. Each client sets its package's number in its
  own `config/filament-cms.php` (Website Starter: `env('CMS_MAX_PAGES', 5)`).
- Local PHP is `php84`. Use `/opt/homebrew/bin/composer`; the Herd composer path has a space in it.
