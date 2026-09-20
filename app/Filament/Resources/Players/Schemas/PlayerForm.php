<?php

namespace App\Filament\Resources\Players\Schemas;

use App\Filament\Support\TeamOptions;
use App\Models\Player;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class PlayerForm
{
    /** @var array<int, string> */
    public const POSITIONS = Player::POSITIONS;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('team_id')
                    ->options(fn (): array => TeamOptions::for())
                    ->required()
                    ->searchable()
                    ->preload(),
                ...static::teamAgnosticFields(),
            ]);
    }

    /**
     * Shared with PlayersRelationManager, which reuses everything except
     * the team_id Select (the relationship sets it there instead).
     *
     * @return array<int, Component>
     */
    public static function teamAgnosticFields(): array
    {
        return [
            TextInput::make('name')
                ->required(),
            Select::make('position')
                ->options(array_combine(self::POSITIONS, self::POSITIONS))
                ->required()
                ->native(false),
            DatePicker::make('birth_date'),
            TextInput::make('shirt_number')
                ->numeric()
                ->required()
                ->minValue(1)
                ->maxValue(99)
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: function (Unique $rule, Get $get, $livewire) {
                        // team_id isn't part of the schema inside PlayersRelationManager
                        // (it's removed from teamAgnosticFields() and set by the
                        // relationship instead), so $get('team_id') resolves to
                        // null there. Fall back to the RelationManager's owner
                        // Team so the scoped uniqueness check still works.
                        $teamId = $get('team_id')
                            ?? ($livewire instanceof RelationManager ? $livewire->getOwnerRecord()->getKey() : null);

                        return $rule->where('team_id', $teamId);
                    },
                ),
        ];
    }
}
