<?php

namespace App\Filament\Club\Resources\Suggestions\Pages;

use App\Filament\Club\Resources\Suggestions\SuggestionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuggestions extends ListRecords
{
    protected static string $resource = SuggestionResource::class;

    protected static ?string $title = 'Buzón de sugerencias';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Enviar una sugerencia'),
        ];
    }
}
