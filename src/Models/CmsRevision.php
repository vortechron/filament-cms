<?php

namespace Vortechron\FilamentCms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One saved version of a page, kept so a developer can undo a mistake.
 *
 * @property string $key
 * @property array<string, array<string, mixed>> $content
 */
class CmsRevision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'cms_revisions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('auth.providers.users.model');

        return $this->belongsTo($model);
    }
}
