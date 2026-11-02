<?php

namespace Vortechron\FilamentCms;

use InvalidArgumentException;

/**
 * A page the client edits: a key, a label and its sections, in the order the
 * page draws them. The client fills the sections in; they cannot add, move
 * or remove them, so the design stays as built.
 */
abstract class CmsPage
{
    /**
     * The key the page is saved under and read with: cms('home').
     */
    abstract public static function key(): string;

    /**
     * The name the client sees in the panel menu.
     */
    abstract public static function label(): string;

    /**
     * @return list<CmsSection>
     */
    abstract public function sections(): array;

    public function section(string $key): CmsSection
    {
        foreach ($this->sections() as $section) {
            if ($section->key === $key) {
                return $section;
            }
        }

        throw new InvalidArgumentException(sprintf('The page [%s] has no section [%s].', static::key(), $key));
    }
}
