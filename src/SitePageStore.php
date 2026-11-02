<?php

namespace Vortechron\FilamentCms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Vortechron\FilamentBlockEditor\Content\PageContent;
use Vortechron\FilamentCms\Models\CmsRevision;
use Vortechron\FilamentCms\Models\SitePage;

/**
 * Saves what the client built for a site page, cleaned by the page builder's
 * rules, and keeps the newest versions so a developer can bring one back.
 */
final class SitePageStore
{
    /**
     * @param  array<mixed>  $sections
     */
    public function save(SitePage $page, array $sections, ?Authenticatable $user): SitePage
    {
        $clean = PageContent::normalize($sections);

        return DB::transaction(function () use ($page, $clean, $user): SitePage {
            $page->forceFill(['sections' => $clean])->save();

            $latest = CmsRevision::query()->where('key', $page->revisionKey())->latest('id')->value('content');

            if ($latest !== $clean) {
                CmsRevision::query()->create([
                    'key' => $page->revisionKey(),
                    'content' => $clean,
                    'user_id' => $user?->getAuthIdentifier(),
                ]);

                $keep = max(1, (int) config('filament-cms.revisions', 20));
                $newest = CmsRevision::query()->where('key', $page->revisionKey())->latest('id')->limit($keep)->pluck('id');
                CmsRevision::query()->where('key', $page->revisionKey())->whereNotIn('id', $newest)->delete();
            }

            return $page;
        });
    }

    public function restore(SitePage $page, CmsRevision $revision, ?Authenticatable $user): SitePage
    {
        abort_unless($revision->key === $page->revisionKey(), 404);

        return $this->save($page, $revision->content, $user);
    }
}
