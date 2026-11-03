<?php

namespace Vortechron\FilamentCms\Forms;

use Vortechron\FilamentBlockEditor\Forms\FormDefinition;

/**
 * A stored form block found by its `_form` key (FormLocator).
 */
final readonly class LocatedForm
{
    public function __construct(
        public FormDefinition $form,
        public string $key,
        public string $blockId,
    ) {}
}
