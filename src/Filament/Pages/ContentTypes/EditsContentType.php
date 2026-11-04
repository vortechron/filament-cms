<?php

namespace Vortechron\FilamentCms\Filament\Pages\ContentTypes;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Entries\StoredEntryTypes;

/**
 * The create and edit pages of one content type: the form, its Save
 * button, and saving through StoredEntryTypes::put(). Saved, it goes back
 * to the list, so the menu shows the new or renamed type.
 *
 * @property-read Schema $form
 */
trait EditsContentType
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return ListContentTypes::canAccess();
    }

    /**
     * The key of the type being edited; null on the create page.
     */
    abstract protected function savedKey(): ?string;

    public function form(Schema $schema): Schema
    {
        return $schema->components(ContentTypeForm::components($this->savedKey() !== null))->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('filament-cms::cms.save'))->submit('save')->keyBindings(['mod+s']),
                        Action::make('cancel')->label(__('filament-cms::types.cancel'))->color('gray')->url(ListContentTypes::getUrl()),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $problem = StoredEntryTypes::put($this->form->getState(), $this->savedKey(), app(EntryTypes::class)->inCode(), Filament::auth()->user());

        if ($problem !== null) {
            Notification::make()->danger()->title($problem)->send();

            return;
        }

        Notification::make()->success()->title(__('filament-cms::types.saved'))->send();
        $this->redirect(ListContentTypes::getUrl());
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [ListContentTypes::getUrl() => __('filament-cms::types.title'), '' => $this->getTitle()];
    }
}
