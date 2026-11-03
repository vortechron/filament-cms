<?php

namespace Vortechron\FilamentCms\Tests\Concerns;

use Illuminate\Testing\TestResponse;
use Vortechron\FilamentBlockEditor\BlockNote\Blocks;
use Vortechron\FilamentBlockEditor\Forms\FormTimestamp;
use Vortechron\FilamentCms\DocumentStore;
use Vortechron\FilamentCms\Models\Document;
use Vortechron\FilamentCms\Models\SitePage;

/**
 * A page with a form block, and a visitor sending it.
 */
trait MakesForms
{
    /**
     * @param  list<array<string, mixed>>  $actions
     * @return array<string, mixed>
     */
    protected static function exampleForm(array $actions = []): array
    {
        return [
            'name' => 'Borang pertanyaan',
            'fields' => [
                ['id' => 'f1', 'type' => 'text', 'name' => 'nama', 'label' => 'Nama', 'required' => true],
                ['id' => 'f2', 'type' => 'tel', 'name' => 'telefon', 'label' => 'Telefon', 'required' => true],
                ['id' => 'f3', 'type' => 'select', 'name' => 'servis', 'label' => 'Servis', 'options' => ['Fotostat', 'Parcel']],
                ['id' => 'f4', 'type' => 'textarea', 'name' => 'mesej', 'label' => 'Mesej'],
                ['id' => 'f5', 'type' => 'checkboxes', 'name' => 'hari', 'label' => 'Hari', 'options' => ['Isnin', 'Selasa']],
                ['id' => 'f6', 'type' => 'consent', 'name' => 'setuju', 'label' => 'Saya setuju dihubungi.', 'required' => true],
            ],
            'success' => 'Terima kasih.',
            'actions' => $actions,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $form
     * @return array<string, mixed>
     */
    protected static function formSection(string $sectionId = 'sec00001', string $blockId = 'frm00001', ?array $form = null): array
    {
        return ['id' => $sectionId, 'type' => 'rich', 'data' => ['document' => [
            Blocks::paragraph('Hubungi kami'),
            ['id' => $blockId, ...Blocks::form($form ?? self::exampleForm())],
        ]]];
    }

    /**
     * @param  array<string, mixed>|null  $form
     */
    protected function pageWithForm(?array $form = null, bool $published = true, string $path = '/hubungi'): SitePage
    {
        return SitePage::query()->create([
            'title' => 'Hubungi',
            'path' => $path,
            'is_published' => $published,
            'sections' => [self::formSection(form: $form)],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    protected function part(string $kind, array $sections): Document
    {
        $part = Document::findOrCreatePart($kind);
        app(DocumentStore::class)->save($part, $sections, null, developer: true);

        return $part;
    }

    /**
     * What a person sends: every required field filled.
     *
     * @return array<string, mixed>
     */
    protected static function goodInput(array $changes = []): array
    {
        return [
            'nama' => 'Siti',
            'telefon' => '012-345 6789',
            'servis' => 'Parcel',
            'mesej' => 'Berapa harga?',
            'hari' => ['Isnin'],
            'setuju' => '1',
            ...$changes,
        ];
    }

    /**
     * A signed `_t` from some seconds ago.
     */
    protected function timestamp(int $secondsAgo = 10): string
    {
        $now = now();
        $this->travelTo($now->copy()->subSeconds($secondsAgo));
        $timestamp = FormTimestamp::issue();
        $this->travelTo($now);

        return $timestamp;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function send(Document $document, array $input = [], string $key = 'sec00001.frm00001', array $hidden = []): TestResponse
    {
        return $this->post(route('filament-cms.forms.submit', $document), [
            '_form' => $key,
            '_t' => $this->timestamp(),
            '_website' => '',
            ...$hidden,
            ...($input === [] ? self::goodInput() : $input),
        ]);
    }
}
