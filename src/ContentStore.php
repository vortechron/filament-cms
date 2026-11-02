<?php

namespace Vortechron\FilamentCms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Vortechron\FilamentCms\Models\CmsContent;
use Vortechron\FilamentCms\Models\CmsRevision;

/**
 * Saves what the client typed for one page, and keeps the newest versions so
 * a developer can bring an older one back.
 */
final class ContentStore
{
    /**
     * @param  array<string, array<string, mixed>>  $content
     */
    public function save(string $key, array $content, ?Authenticatable $user): CmsContent
    {
        return DB::transaction(function () use ($key, $content, $user): CmsContent {
            $saved = CmsContent::query()->updateOrCreate(['key' => $key], ['content' => $content]);

            CmsRevision::query()->create([
                'key' => $key,
                'content' => $content,
                'user_id' => $user?->getAuthIdentifier(),
            ]);

            $this->prune($key);

            return $saved;
        });
    }

    /**
     * Makes an older version the current one. The restore is itself saved as
     * a new version, so it can be undone too.
     */
    public function restore(CmsRevision $revision, ?Authenticatable $user): CmsContent
    {
        return $this->save($revision->key, $revision->content, $user);
    }

    private function prune(string $key): void
    {
        $keep = max(1, (int) config('filament-cms.revisions', 20));

        $newest = CmsRevision::query()->where('key', $key)->latest('id')->limit($keep)->pluck('id');

        CmsRevision::query()->where('key', $key)->whereNotIn('id', $newest)->delete();
    }
}
