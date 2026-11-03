<?php

namespace Vortechron\FilamentCms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\SitePage;

/**
 * Saves a site page through DocumentStore, for callers written before
 * documents existed. It throws a ValidationException when the value is
 * refused. New code calls DocumentStore directly.
 */
final class SitePageStore
{
    public function __construct(private readonly DocumentStore $documents) {}

    /**
     * @param  array<mixed>  $sections  a list of sections, or a page value of any format
     */
    public function save(SitePage $page, array $sections, ?Authenticatable $user): SitePage
    {
        return $this->saved($page, $this->documents->save($page, $sections, $user));
    }

    public function restore(SitePage $page, CmsRevision $revision, ?Authenticatable $user): SitePage
    {
        return $this->saved($page, $this->documents->restore($page, $revision, $user));
    }

    /**
     * @param  array{ok: bool, errors?: array<string, string>}  $result
     */
    private function saved(SitePage $page, array $result): SitePage
    {
        if (! $result['ok']) {
            throw ValidationException::withMessages($result['errors'] ?? []);
        }

        return $page;
    }
}
