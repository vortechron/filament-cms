<?php

namespace Vortechron\FilamentCms\Filament\Resources\FormSubmissions;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Vortechron\FilamentCms\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use Vortechron\FilamentCms\Filament\Resources\FormSubmissions\Pages\ViewFormSubmission;
use Vortechron\FilamentCms\Forms\SubmissionRows;
use Vortechron\FilamentCms\Models\FormSubmission;

/**
 * The "Borang" screen: what visitors sent with the site's form blocks,
 * newest first. Opening one marks it read. Submissions are only read,
 * marked, deleted or downloaded as CSV here; they are never made or
 * changed by hand.
 */
class FormSubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static ?string $slug = 'form-submissions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = -11;

    public static function getModelLabel(): string
    {
        return __('filament-cms::forms.submission');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-cms::forms.submissions');
    }

    public static function getNavigationBadge(): ?string
    {
        $new = FormSubmission::query()->where('status', FormSubmission::NEW)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components(fn (FormSubmission $record): array => [
            Section::make(__('filament-cms::forms.answers'))->schema(array_map(
                fn (array $row, int $index): TextEntry => TextEntry::make("answer_{$index}")
                    ->label($row['label'])
                    ->state($row['value'] !== '' ? $row['value'] : '—')
                    ->extraAttributes(['style' => 'white-space: pre-line']),
                $rows = SubmissionRows::of($record),
                array_keys($rows),
            )),
            Section::make(__('filament-cms::forms.details'))->columns(2)->schema([
                TextEntry::make('form_name')->label(__('filament-cms::forms.form'))->state(self::formName($record)),
                TextEntry::make('status')->label(__('filament-cms::forms.status'))->badge()->color(self::statusColor(...))->formatStateUsing(self::statusLabel(...)),
                TextEntry::make('created_at')->label(__('filament-cms::forms.received'))->dateTime(),
                TextEntry::make('meta.page')->label(__('filament-cms::forms.page'))->placeholder('—'),
                TextEntry::make('meta.user_agent')->label(__('filament-cms::forms.browser'))->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn ($query) => $query->latest('created_at')->latest('id'))
            ->emptyStateHeading(__('filament-cms::forms.empty'))
            ->columns([
                TextColumn::make('created_at')->label(__('filament-cms::forms.received'))->dateTime()->sortable(),
                TextColumn::make('form_name')->label(__('filament-cms::forms.form'))->state(self::formName(...))->searchable(),
                TextColumn::make('summary')->label(__('filament-cms::forms.summary'))->state(self::summary(...))->limit(80),
                TextColumn::make('status')->label(__('filament-cms::forms.status'))->badge()->color(self::statusColor(...))->formatStateUsing(self::statusLabel(...)),
            ])
            ->filters([
                SelectFilter::make('form_key')->label(__('filament-cms::forms.form'))->options(fn (): array => self::formOptions()),
                SelectFilter::make('status')->label(__('filament-cms::forms.status'))->options(self::statusOptions()),
            ])
            ->recordActions([ViewAction::make(), self::markAction(FormSubmission::SPAM), self::markAction(FormSubmission::NEW), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    /**
     * One option per stored form, by its key: two forms with the same name
     * stay apart, and a form with no name can be picked. The label is its
     * newest name and the pages it was sent from, newest first ("Borang
     * pertanyaan · /hubungi"); a header form lists every page.
     *
     * @return array<string, string>
     */
    public static function formOptions(): array
    {
        $newest = FormSubmission::query()->selectRaw('max(id) as id')->groupBy('form_key', 'document_id')->pluck('id');

        return FormSubmission::query()->whereKey($newest)->latest('id')->get()->groupBy('form_key')
            ->map(function ($rows): array {
                $pages = $rows->map(fn (FormSubmission $row): ?string => is_string($row->meta['page'] ?? null) ? $row->meta['page'] : null)->filter()->unique();
                $list = $pages->take(3)->implode(', ').($pages->count() > 3 ? ', …' : '');

                return ['name' => self::formName($rows->first()), 'pages' => $list !== '' ? $list : '—'];
            })
            ->sortBy([fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']), fn (array $a, array $b): int => strnatcasecmp($a['pages'], $b['pages'])])
            ->map(fn (array $form): string => "{$form['name']} · {$form['pages']}")
            ->all();
    }

    /**
     * "Tanda spam" or "Tanda baru": sets the status, hidden when it is set.
     */
    public static function markAction(string $status): Action
    {
        return Action::make($status)
            ->label(__("filament-cms::forms.mark_{$status}"))
            ->icon($status === FormSubmission::SPAM ? Heroicon::OutlinedNoSymbol : Heroicon::OutlinedEnvelope)
            ->visible(fn (FormSubmission $record): bool => $record->status !== $status)
            ->action(fn (FormSubmission $record) => $record->update(['status' => $status]));
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return array_combine(FormSubmission::STATUSES, array_map(self::statusLabel(...), FormSubmission::STATUSES));
    }

    public static function statusLabel(?string $state): string
    {
        return in_array($state, FormSubmission::STATUSES, true) ? __("filament-cms::forms.statuses.{$state}") : (string) $state;
    }

    public static function formName(FormSubmission $record): string
    {
        return $record->form_name ?? __('filament-cms::forms.form');
    }

    private static function statusColor(?string $state): string
    {
        return match ($state) {
            FormSubmission::NEW => 'primary',
            FormSubmission::SPAM => 'danger',
            default => 'gray',
        };
    }

    /**
     * The first few values, for the list.
     */
    private static function summary(FormSubmission $record): string
    {
        $values = array_filter(array_map(fn (mixed $value): string => SubmissionRows::text($value), array_slice($record->data ?? [], 0, 3)));

        return implode(' · ', $values);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormSubmissions::route('/'),
            'view' => ViewFormSubmission::route('/{record}'),
        ];
    }
}
