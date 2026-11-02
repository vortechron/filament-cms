<?php

namespace Vortechron\FilamentCms\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The saved content of one page, keyed by the page key.
 *
 * @property string $key
 * @property array<string, array<string, mixed>> $content
 */
class CmsContent extends Model
{
    protected $table = 'cms_contents';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }
}
