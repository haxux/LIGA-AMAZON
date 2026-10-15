<?php

namespace App\Filament\Resources\Suggestions;

use App\Filament\Resources\Suggestions\Pages\ListSuggestions;
use App\Models\Suggestion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * El buzón de sugerencias, visto por el administrador: lo que los técnicos
 * piden, de quién viene y en qué punto está. Se lee, se marca y se borra; no se
 * escribe aquí.
 */
class SuggestionResource extends Resource
{
    protected static ?string $model = Suggestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLightBulb;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Suggestions';

    protected static ?string $modelLabel = 'suggestion';

    public static function canCreate(): bool
    {
        // Las escriben los técnicos desde su panel.
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $new = Suggestion::query()->where('status', Suggestion::STATUS_NEW)->count();

        return $new === 0 ? null : (string) $new;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Sent')->date('d/m/Y')->sortable(),
                TextColumn::make('user.name')->label('Coach')->searchable(),
                TextColumn::make('user.club.name')->label('Club')->placeholder('—'),
                TextColumn::make('title')->searchable()->wrap()->limit(60),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Suggestion::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Suggestion::STATUS_DONE => 'success',
                        Suggestion::STATUS_READ => 'info',
                        default => 'warning',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(Suggestion::STATUSES),
            ])
            ->recordActions([
                // Los campos de sólo lectura que el propio ViewAction rellena
                // desde el registro: el texto entero, que en la tabla va cortado.
                ViewAction::make()
                    ->modalHeading(fn (Suggestion $record): string => $record->title)
                    ->schema([
                        TextInput::make('title')->label('Title'),
                        Textarea::make('body')->label('Idea')->rows(10),
                    ]),
                Action::make('read')
                    ->label('Mark read')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (Suggestion $record): bool => $record->status === Suggestion::STATUS_NEW)
                    ->action(fn (Suggestion $record) => $record->update(['status' => Suggestion::STATUS_READ])),
                Action::make('done')
                    ->label('Mark applied')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Suggestion $record): bool => $record->status !== Suggestion::STATUS_DONE)
                    ->action(fn (Suggestion $record) => $record->update(['status' => Suggestion::STATUS_DONE])),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuggestions::route('/'),
        ];
    }
}
