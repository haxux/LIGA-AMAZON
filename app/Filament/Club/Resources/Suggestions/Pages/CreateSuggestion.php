<?php

namespace App\Filament\Club\Resources\Suggestions\Pages;

use App\Filament\Club\Resources\Suggestions\SuggestionResource;
use App\Models\Suggestion;
use Filament\Resources\Pages\CreateRecord;

class CreateSuggestion extends CreateRecord
{
    protected static string $resource = SuggestionResource::class;

    protected static ?string $title = 'Enviar una sugerencia';

    /**
     * Lo que el técnico no elige: de quién es y en qué estado nace.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['status'] = Suggestion::STATUS_NEW;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return SuggestionResource::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Sugerencia enviada. ¡Gracias por ayudar a mejorar el proyecto!';
    }
}
