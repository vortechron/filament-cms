<?php

namespace Vortechron\FilamentCms\Filament\Resources\FormSubmissions\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Vortechron\FilamentCms\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Vortechron\FilamentCms\Models\FormSubmission;

/**
 * One submission: each field's label and value. Opening a new one marks
 * it read.
 */
class ViewFormSubmission extends ViewRecord
{
    protected static string $resource = FormSubmissionResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var FormSubmission $submission */
        $submission = $this->getRecord();

        if ($submission->status === FormSubmission::NEW) {
            $submission->update(['status' => FormSubmission::READ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            FormSubmissionResource::markAction(FormSubmission::SPAM),
            FormSubmissionResource::markAction(FormSubmission::NEW),
            DeleteAction::make(),
        ];
    }
}
