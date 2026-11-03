<?php

namespace Vortechron\FilamentCms\Tests\Feature;

use Vortechron\FilamentBlockEditor\Forms\FormEndpoint;
use Vortechron\FilamentCms\Forms\CmsFormEndpoint;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Tests\Concerns\MakesForms;
use Vortechron\FilamentCms\Tests\TestCase;

class FormEndpointTest extends TestCase
{
    use MakesForms;

    public function test_a_form_on_a_page_posts_to_that_page(): void
    {
        $page = $this->pageWithForm();

        $html = $this->get('/hubungi')->assertOk()->getContent();

        $this->assertInstanceOf(CmsFormEndpoint::class, app(FormEndpoint::class));
        $this->assertStringContainsString('action="'.url('/_cms/forms/'.$page->getKey()).'"', $html);
        $this->assertStringContainsString('name="_form" value="sec00001.frm00001"', $html);
        $this->assertStringNotContainsString(' disabled', $html);
    }

    public function test_a_form_in_the_header_posts_to_the_page_it_is_shown_on(): void
    {
        $this->part(Document::HEADER, [self::formSection('hdr00001', 'frmhdr01')]);
        $first = $this->pageWithForm(path: '/hubungi');
        $second = $this->pageWithForm(path: '/tentang');

        $this->assertMatchesRegularExpression($this->headerForm($first), $this->get('/hubungi')->getContent());
        $this->assertMatchesRegularExpression($this->headerForm($second), $this->get('/tentang')->getContent());
    }

    private function headerForm(Document $page): string
    {
        return '~<div data-part="header".*action="'.preg_quote(url('/_cms/forms/'.$page->getKey()), '~').'"\s+data-form="hdr00001\.frmhdr01"~s';
    }
}
