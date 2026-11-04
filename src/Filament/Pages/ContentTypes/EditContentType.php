<?php

namespace Vortechron\FilamentCms\Filament\Pages\ContentTypes;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Livewire\Attributes\Locked;
use Vortechron\FilamentCms\Entries\EntryTypes;
use Vortechron\FilamentCms\Entries\StoredEntryTypes;

/**
 * One saved content type, /admin/content-types/{type}/edit: its form,
 * and Delete (refused while it still has entries). Its key never changes.
 */
class EditContentType extends Page
{
    use EditsContentType;

    protected static ?string $slug = 'content-types/{type}/edit';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public string $type = '';

    public function mount(string $type): void
    {
        $definition = collect(StoredEntryTypes::definitions())->firstWhere('key', $type);
        abort_if($definition === null, 404);

        $this->type = $type;
        $this->form->fill($definition);
    }

    public function getTitle(): string
    {
        return __('filament-cms::types.edit', ['type' => (string) ($this->data['label'] ?? $this->type)]);
    }

    protected function savedKey(): ?string
    {
        return $this->type;
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'content-types.edit';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label(__('filament-cms::types.delete'))
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(__('filament-cms::types.delete_help'))
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);
                    $problem = StoredEntryTypes::remove($this->type, app(EntryTypes::class)->inCode(), Filament::auth()->user());

                    if ($problem !== null) {
                        Notification::make()->danger()->title($problem)->send();

                        return;
                    }

                    Notification::make()->success()->title(__('filament-cms::types.deleted'))->send();
                    $this->redirect(ListContentTypes::getUrl());
                }),
        ];
    }
}
