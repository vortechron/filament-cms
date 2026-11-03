<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Vortechron\FilamentBlockEditor\Blocks\Field;
use Vortechron\FilamentCms\Entries\EntryType;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Tests\TestCase;

class EntryTypesTest extends TestCase
{
    public function test_a_type_is_described_fluently(): void
    {
        $type = EntryType::make('product')
            ->label('Produk')
            ->pluralLabel('Senarai produk')
            ->icon('heroicon-o-cube')
            ->fields([Field::text('price'), Field::image('photo')])
            ->path('/Produk Kami')
            ->body(true)
            ->sort('manual');

        $this->assertSame('product', $type->key);
        $this->assertSame('Produk', $type->getLabel());
        $this->assertSame('Senarai produk', $type->getPluralLabel());
        $this->assertSame('heroicon-o-cube', $type->getIcon());
        $this->assertSame(['price', 'photo'], array_map(fn (Field $field): string => $field->name, $type->getFields()));
        $this->assertSame('/produk-kami', $type->getPath());
        $this->assertTrue($type->hasBody());
        $this->assertSame('manual', $type->getSort());
    }

    public function test_a_type_has_safe_defaults(): void
    {
        $type = EntryType::make('event');

        $this->assertSame('Event', $type->getLabel());
        $this->assertSame('Event', $type->getPluralLabel());
        $this->assertSame([], $type->getFields());
        $this->assertNull($type->getPath());
        $this->assertFalse($type->hasBody());
        $this->assertSame('newest', $type->getSort());
    }

    public function test_a_type_without_a_path_has_no_pages(): void
    {
        $this->assertNull(EntryType::make('faq')->path('/soalan')->path(null)->getPath());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badKeys(): array
    {
        return [
            'capital' => ['Product'],
            'digit first' => ['1product'],
            'underscore' => ['my_product'],
            'empty' => [''],
            'too long' => [str_repeat('a', 41)],
            'space' => ['my product'],
        ];
    }

    #[DataProvider('badKeys')]
    public function test_a_bad_key_is_refused(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);

        EntryType::make($key);
    }

    public function test_a_long_key_of_forty_characters_is_fine(): void
    {
        $this->assertSame(str_repeat('a', 40), EntryType::make(str_repeat('a', 40))->key);
        $this->assertSame('home-made-1', EntryType::make('home-made-1')->key);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badPaths(): array
    {
        return [
            'home' => ['/'],
            'empty' => [''],
            'admin' => ['/admin'],
            'inside admin' => ['/admin/produk'],
            'livewire' => ['/livewire'],
            'storage' => ['/storage'],
            'up' => ['/up'],
            'filament' => ['/filament'],
            'vendor' => ['/vendor'],
            'mcp' => ['/mcp'],
            'inside mcp' => ['/mcp/produk'],
        ];
    }

    #[DataProvider('badPaths')]
    public function test_a_path_that_clashes_with_the_site_is_refused(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        EntryType::make('product')->path($path);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reservedNames(): array
    {
        return ['title' => ['title'], 'url' => ['url'], 'slug' => ['slug']];
    }

    #[DataProvider('reservedNames')]
    public function test_reserved_field_names_are_refused(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        EntryType::make('product')->fields([Field::text($name)]);
    }

    public function test_a_field_name_must_work_as_a_dynamic_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EntryType::make('product')->fields([Field::text('mainPhoto')]);
    }

    public function test_a_field_name_may_only_be_used_once(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EntryType::make('product')->fields([Field::text('price'), Field::number('price')]);
    }

    public function test_only_the_three_orders_are_known(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EntryType::make('product')->sort('random');
    }

    public function test_the_registry_keeps_types_by_key(): void
    {
        $types = app(EntryTypes::class);
        $this->assertSame($types, app(EntryTypes::class));
        $this->assertSame([], $types->all());

        $types->register(EntryType::make('product')->path('/produk'))->register(EntryType::make('faq'));

        $this->assertTrue($types->has('product'));
        $this->assertSame('faq', $types->get('faq')?->key);
        $this->assertNull($types->get('nothing'));
        $this->assertSame(['product', 'faq'], array_keys($types->all()));

        $types->forget('faq');
        $this->assertFalse($types->has('faq'));
    }

    public function test_two_types_may_not_share_a_path(): void
    {
        $types = app(EntryTypes::class)->register(EntryType::make('product')->path('/produk'));

        $this->expectException(InvalidArgumentException::class);

        $types->register(EntryType::make('item')->path('/produk'));
    }
}
