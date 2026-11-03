<?php

namespace Vortechron\FilamentCms\Filament\Resources\FormSubmissions\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Vortechron\FilamentCms\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Vortechron\FilamentCms\Forms\SubmissionCsv;

/**
 * Submissions come from visitors, so this screen has no "new" button. The
 * CSV holds the rows the filters show.
 */
class ListFormSubmissions extends ListRecords
{
    protected static string $resource = FormSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('filament-cms::forms.export'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => app(SubmissionCsv::class)->download($this->getFilteredSortedTableQuery())),
        ];
    }
}
