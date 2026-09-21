<?php

namespace App\Filament\Club\Resources\Budget\Pages;

use App\Filament\Club\Resources\Budget\BudgetResource;
use App\Models\BudgetMovement;
use Filament\Resources\Pages\CreateRecord;

class CreateBudgetProposal extends CreateRecord
{
    protected static string $resource = BudgetResource::class;

    protected static ?string $title = 'Proponer un movimiento';

    /**
     * El club, la temporada y el estado no los elige el técnico: son su club,
     * la temporada vigente y "propuesto". Dejarlos en el formulario sería
     * ofrecer decisiones que no son suyas.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['club_id'] = auth()->user()?->club_id;
        $data['season_id'] = BudgetResource::currentSeasonId();
        $data['status'] = BudgetMovement::STATUS_PROPOSED;
        $data['created_by'] = auth()->id();

        return $data;
    }
}
