<?php

namespace App\Filament\Resources\Divisions\RelationManagers;

use App\Models\StandingZone;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StandingZonesRelationManager extends RelationManager
{
    protected static string $relationship = 'standingZones';

    protected static ?string $title = 'Standings zones';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->required()
                    ->helperText('Shown in the public table legend, e.g. Ascenso, Descenso, Fase europea.'),
                Select::make('color')
                    ->options(collect(StandingZone::COLORS)->map(fn (array $color): string => $color['label']))
                    ->required()
                    ->native(false)
                    ->default('green'),
                TextInput::make('from_position')
                    ->label('First position')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(99)
                    ->live()
                    ->rule(static::noOverlapRule(...)),
                TextInput::make('to_position')
                    ->label('Last position')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(99)
                    ->rule(static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        if (filled($value) && filled($get('from_position')) && (int) $value < (int) $get('from_position')) {
                            $fail('The last position cannot be above the first one.');
                        }
                    }),
            ]);
    }

    /**
     * Mirrors StandingZone's own overlap guard, so the operator gets a field
     * error instead of an exception surfacing from the model. The record
     * being edited is excluded — a band always overlaps itself.
     */
    private function noOverlapRule(Get $get, ?Model $record): Closure
    {
        $divisionId = $this->getOwnerRecord()->getKey();

        return function (string $attribute, mixed $value, Closure $fail) use ($get, $record, $divisionId): void {
            if (blank($value) || blank($get('to_position'))) {
                return;
            }

            $overlapping = StandingZone::query()
                ->where('division_id', $divisionId)
                ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->where('from_position', '<=', (int) $get('to_position'))
                ->where('to_position', '>=', (int) $value)
                ->first();

            if ($overlapping !== null) {
                $fail("Positions {$overlapping->from_position}–{$overlapping->to_position} already belong to “{$overlapping->label}”.");
            }
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('from_position')
            ->columns([
                TextColumn::make('label'),
                TextColumn::make('color')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StandingZone::COLORS[$state]['label'] ?? $state),
                TextColumn::make('from_position')->label('From')->alignCenter(),
                TextColumn::make('to_position')->label('To')->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
