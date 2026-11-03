<?php

namespace Vortechron\FilamentCms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a visitor sent with a form block, kept from the stored form's fields
 * only (Forms\FormSubmissionHandler). The "Borang" screen lists them.
 *
 * `meta` holds the page path, the browser (cut to 300 characters) and a
 * keyed hash of the IP address, never the address itself.
 *
 * @property int $id
 * @property int|null $document_id
 * @property string $form_key
 * @property string|null $form_name
 * @property array<string, mixed> $data
 * @property array<string, mixed>|null $meta
 * @property string $status
 */
class FormSubmission extends Model
{
    public const NEW = 'new';

    public const READ = 'read';

    public const SPAM = 'spam';

    public const STATUSES = [self::NEW, self::READ, self::SPAM];

    protected $table = 'cms_form_submissions';

    protected $guarded = [];

    protected $attributes = ['status' => self::NEW];

    protected function casts(): array
    {
        return ['data' => 'array', 'meta' => 'array', 'document_id' => 'integer'];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
