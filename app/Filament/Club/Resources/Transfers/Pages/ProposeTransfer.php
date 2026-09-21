<?php

namespace App\Filament\Club\Resources\Transfers\Pages;

use App\Filament\Club\Resources\Transfers\Schemas\TransferProposalForm;
use App\Filament\Club\Resources\Transfers\TransferHistoryResource;
use App\Models\Player;
use App\Models\Transfer;
use App\Services\SeasonResolver;
use Filament\Resources\Pages\CreateRecord;

class ProposeTransfer extends CreateRecord
{
    protected static string $resource = TransferHistoryResource::class;

    protected static ?string $title = 'Proponer un fichaje';

    /**
     * El club del técnico, la temporada y el estado no los elige él: son su
     * club, la temporada vigente y "propuesto". Los dos extremos salen de la
     * dirección que eligió y del club del jugador, así que tampoco hay que
     * pedírselos.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $mine = auth()->user()?->club_id;
        $direction = $data['direction'] ?? TransferProposalForm::IN;
        unset($data['direction']);

        $playerClubId = Player::query()->whereKey($data['player_id'] ?? null)->value('club_id');

        if ($direction === TransferProposalForm::IN) {
            $data['from_club_id'] = $playerClubId;
            $data['to_club_id'] = $mine;
        } else {
            $data['from_club_id'] = $mine;
            $data['to_club_id'] = ($data['scope'] ?? null) === Transfer::SCOPE_EXTERNAL ? null : ($data['to_club_id'] ?? null);
        }

        $data['season_id'] = app(SeasonResolver::class)->active()?->getKey();
        $data['status'] = Transfer::STATUS_PROPOSED;
        $data['proposed_by'] = auth()->id();
        $data['fee'] = ($data['type'] ?? null) === Transfer::TYPE_LOAN ? 0 : ($data['fee'] ?? 0);

        return $data;
    }
}
