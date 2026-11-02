<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentCms\Tests\TestCase;

class ReadContentTest extends TestCase
{
    public function test_a_page_shows_its_default_text_before_anything_is_saved(): void
    {
        $hero = cms('home')->section('hero');

        $this->assertSame('Sambal Dapur Maya', $hero->title);
        $this->assertSame('Sambal Dapur Maya', $hero['title']);
        $this->assertSame([['name' => 'Kecil', 'price' => '15'], ['name' => 'Besar', 'price' => '25']], cms('home')->section('pakej')->packages);
    }
}
