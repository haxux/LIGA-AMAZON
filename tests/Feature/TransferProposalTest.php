<?php

namespace Tests\Feature;

use App\Filament\Club\Resources\Budget\BudgetResource;
use App\Filament\Club\Resources\Transfers\Pages\ProposeTransfer;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Filament\Resources\Transfers\TransferResource;
use App\Models\BudgetMovement;
use App\Models\Club;
use App\Models\Conversation;
use App\Models\Player;
use App\Models\Season;
use App\Models\SquadMembership;
use App\Models\Team;
use App\Models\Transfer;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\TransferService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El técnico propone a la dirección, siempre hacia fuera de la liga —lo de
 * dentro se negocia por el chat—, y el administrador acepta rellenando lo que
 * la propuesta no podía saber. Al guardar eso se mueve todo de una vez.
 */
class TransferProposalTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Club $club;

    private Team $team;

    private User $coach;

    private User $admin;

    private Player $mine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::factory()->create(['is_current' => true, 'name' => '2026/27']);
        $this->club = Club::factory()->create(['name' => 'Manaos FC', 'initial_balance' => 1_000_000]);
        $this->team = Team::factory()->create(['club_id' => $this->club->id, 'season_id' => $this->season->id]);

        $this->coach = User::factory()->coachOf($this->club)->create(['name' => 'Técnico Uno']);
        $this->admin = User::factory()->create(['name' => 'Presidenta']);

        $this->mine = Player::factory()->create(['club_id' => $this->club->id, 'name' => 'El Mío']);
        SquadMembership::factory()->create(['team_id' => $this->team->id, 'player_id' => $this->mine->id, 'shirt_number' => 4]);
    }

    private function asCoach(): void
    {
        $this->actingAs($this->coach, 'club');
        Filament::setCurrentPanel('club');
    }

    private function balance(): int
    {
        return app(BudgetService::class)->balanceFor($this->club->fresh());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function proposal(array $attributes): Transfer
    {
        $incoming = in_array($attributes['type'], Transfer::INCOMING, true);

        return Transfer::create($attributes + [
            'season_id' => $this->season->id,
            'scope' => Transfer::SCOPE_EXTERNAL,
            'status' => Transfer::STATUS_PROPOSED,
            'from_club_id' => $incoming ? null : $this->club->id,
            'to_club_id' => $incoming ? $this->club->id : null,
            'proposed_by' => $this->coach->id,
        ]);
    }

    // ── Lo que el técnico propone ─────────────────────────────────────────

    public function test_the_coach_asks_to_sign_someone_from_outside(): void
    {
        $this->asCoach();

        Livewire::test(ProposeTransfer::class)
            ->fillForm([
                'type' => Transfer::TYPE_SIGNING,
                'external_player' => 'Rivaldo Nunes',
                'external_club' => 'Palmeiras',
                'fee' => 300_000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $proposal = Transfer::query()->latest('id')->first();

        $this->assertSame(Transfer::STATUS_PROPOSED, $proposal->status);
        $this->assertSame(Transfer::SCOPE_EXTERNAL, $proposal->scope);
        $this->assertSame('Rivaldo Nunes', $proposal->external_player);
        $this->assertNull($proposal->player_id);
        $this->assertSame($this->club->id, $proposal->to_club_id);
        $this->assertNull($proposal->from_club_id);
        $this->assertSame($this->coach->id, $proposal->proposed_by);

        // Y no ha movido nada: ni ficha, ni dinero.
        $this->assertSame(1, Player::query()->count());
        $this->assertSame(0, BudgetMovement::query()->count());
        $this->assertSame(1_000_000, $this->balance());
    }

    public function test_the_coach_puts_one_of_theirs_up_for_sale(): void
    {
        $this->asCoach();

        Livewire::test(ProposeTransfer::class)
            ->fillForm([
                'type' => Transfer::TYPE_SALE,
                'player_id' => $this->mine->id,
                'fee' => 500_000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $proposal = Transfer::query()->latest('id')->first();

        $this->assertSame($this->mine->id, $proposal->player_id);
        $this->assertSame($this->club->id, $proposal->from_club_id);
        $this->assertNull($proposal->to_club_id);
        $this->assertNull($this->mine->fresh()->left_at);
    }

    public function test_a_loan_carries_a_term_and_no_money(): void
    {
        $this->asCoach();

        Livewire::test(ProposeTransfer::class)
            ->fillForm([
                'type' => Transfer::TYPE_LOAN_IN,
                'external_player' => 'Cedido Nunes',
                'external_club' => 'Palmeiras',
                'loan_term' => Transfer::TERM_ONE_YEAR,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $proposal = Transfer::query()->latest('id')->first();

        $this->assertSame(Transfer::TERM_ONE_YEAR, $proposal->loan_term);
        $this->assertSame(0, $proposal->fee);
    }

    /**
     * Fichaje y venta mueven dinero: una propuesta sin cifra no es una
     * propuesta, es una pregunta.
     */
    public function test_the_amount_is_required_when_there_is_money(): void
    {
        $this->asCoach();

        Livewire::test(ProposeTransfer::class)
            ->fillForm([
                'type' => Transfer::TYPE_SIGNING,
                'external_player' => 'Rivaldo Nunes',
                'external_club' => 'Palmeiras',
            ])
            ->call('create')
            ->assertHasFormErrors(['fee']);
    }

    /**
     * Desde el panel sólo se propone hacia fuera de la liga, así que no hay
     * ámbito que elegir: lo de dentro se negocia por el chat.
     */
    public function test_the_form_does_not_ask_for_a_scope(): void
    {
        $this->asCoach();

        $this->assertNull(Livewire::test(ProposeTransfer::class)->instance()->getSchemaComponent('form.scope'));
    }

    // ── Lo que el administrador hace con ella ─────────────────────────────

    public function test_approving_a_signing_creates_the_player_and_moves_the_money(): void
    {
        $proposal = $this->proposal([
            'type' => Transfer::TYPE_SIGNING,
            'external_player' => 'Rivaldo Nunes',
            'external_club' => 'Palmeiras',
            'fee' => 300_000,
        ]);

        app(TransferService::class)->approve($proposal, $this->admin, [
            'player_name' => 'Rivaldo Nunes',
            'position' => 'Forward',
            'specific_position' => 'DC',
            'external_club' => 'Palmeiras',
            'fee' => 280_000,
            'shirt_number' => 11,
        ]);

        $player = Player::query()->where('name', 'Rivaldo Nunes')->first();

        $this->assertNotNull($player, 'la ficha del que llega nace al aceptar');
        $this->assertSame($this->club->id, $player->club_id);
        $this->assertSame('DC', $player->specific_position);

        // Con el dorsal que puso el administrador, no con el primero libre.
        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $this->team->id,
            'player_id' => $player->id,
            'shirt_number' => 11,
        ]);

        // Y con el importe que se cerró, que no tiene por qué ser el pedido.
        $this->assertSame(Transfer::STATUS_EXECUTED, $proposal->fresh()->status);
        $this->assertSame(280_000, $proposal->fresh()->fee);
        $this->assertSame(720_000, $this->balance());
    }

    public function test_approving_a_sale_collects_and_marks_the_player_as_gone(): void
    {
        $proposal = $this->proposal([
            'type' => Transfer::TYPE_SALE,
            'player_id' => $this->mine->id,
            'fee' => 500_000,
        ]);

        app(TransferService::class)->approve($proposal, $this->admin, [
            'external_club' => 'Palmeiras',
            'fee' => 450_000,
        ]);

        $this->assertSame(1_450_000, $this->balance());
        $this->assertNotNull($this->mine->fresh()->left_at);
        $this->assertSame('Palmeiras', $this->mine->fresh()->left_to);
        $this->assertDatabaseMissing('squad_memberships', [
            'team_id' => $this->team->id,
            'player_id' => $this->mine->id,
        ]);
    }

    public function test_approving_a_loan_moves_the_player_and_no_money(): void
    {
        $proposal = $this->proposal([
            'type' => Transfer::TYPE_LOAN_IN,
            'external_player' => 'Cedido Nunes',
            'external_club' => 'Palmeiras',
            'fee' => 0,
            'loan_term' => Transfer::TERM_ONE_YEAR,
        ]);

        app(TransferService::class)->approve($proposal, $this->admin, [
            'player_name' => 'Cedido Nunes',
            'position' => 'Midfielder',
            'external_club' => 'Palmeiras',
            'loan_term' => Transfer::TERM_SIX_MONTHS,
        ]);

        $player = Player::query()->where('name', 'Cedido Nunes')->first();

        $this->assertSame(0, BudgetMovement::query()->count());
        $this->assertSame(1_000_000, $this->balance());
        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $this->team->id,
            'player_id' => $player->id,
            'type' => SquadMembership::TYPE_LOAN,
        ]);
        $this->assertSame(Transfer::TERM_SIX_MONTHS, $proposal->fresh()->loan_term);
    }

    /**
     * REGRESIÓN: el formulario dejaba poner un dorsal ya ocupado y el guardado
     * reventaba con el unique de `squad_memberships` en la cara del
     * administrador. Ahora falla como error del formulario, y la operación
     * entera se queda sin hacer.
     */
    public function test_a_taken_shirt_number_is_refused_and_nothing_moves(): void
    {
        $proposal = $this->proposal([
            'type' => Transfer::TYPE_SIGNING,
            'external_player' => 'Rivaldo Nunes',
            'external_club' => 'Palmeiras',
            'fee' => 300_000,
        ]);

        try {
            app(TransferService::class)->approve($proposal, $this->admin, [
                'player_name' => 'Rivaldo Nunes',
                'position' => 'Forward',
                'external_club' => 'Palmeiras',
                'fee' => 300_000,
                // El 4 es el de El Mío, que ya está en esa plantilla.
                'shirt_number' => 4,
            ]);
            $this->fail('un dorsal ocupado no debería aceptarse');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('4', $exception->validator->errors()->first());
        }

        $this->assertNull(Player::query()->where('name', 'Rivaldo Nunes')->first(), 'la ficha no debe quedarse creada');
        $this->assertSame(Transfer::STATUS_PROPOSED, $proposal->fresh()->status);
        $this->assertSame(0, BudgetMovement::query()->count());
        $this->assertSame(1_000_000, $this->balance());
    }

    public function test_without_a_number_the_first_free_one_is_used(): void
    {
        $proposal = $this->proposal([
            'type' => Transfer::TYPE_SIGNING,
            'external_player' => 'Rivaldo Nunes',
            'external_club' => 'Palmeiras',
            'fee' => 100,
        ]);

        app(TransferService::class)->approve($proposal, $this->admin, [
            'player_name' => 'Rivaldo Nunes',
            'position' => 'Forward',
            'external_club' => 'Palmeiras',
            'fee' => 100,
        ]);

        // El 4 está cogido por El Mío, así que le toca el 1.
        $this->assertDatabaseHas('squad_memberships', [
            'team_id' => $this->team->id,
            'player_id' => Player::query()->where('name', 'Rivaldo Nunes')->value('id'),
            'shirt_number' => 1,
        ]);
    }

    // ── Lo que el técnico lee después ─────────────────────────────────────

    /**
     * El chat es el único aviso que hay: sin correos ni websockets, lo que la
     * dirección decide se cuenta donde el técnico ya mira.
     */
    public function test_the_coach_is_told_in_their_chat(): void
    {
        $proposal = $this->proposal([
            'type' => Transfer::TYPE_SIGNING,
            'external_player' => 'Rivaldo Nunes',
            'external_club' => 'Palmeiras',
            'fee' => 300_000,
        ]);

        app(TransferService::class)->approve($proposal, $this->admin, [
            'player_name' => 'Rivaldo Nunes',
            'position' => 'Forward',
            'external_club' => 'Palmeiras',
            'fee' => 300_000,
        ]);

        $conversation = Conversation::query()->with('participants')->first();
        $message = $conversation->messages()->latest('id')->first();

        $this->assertTrue($conversation->includes($this->coach));
        $this->assertTrue($conversation->includes($this->admin));
        $this->assertSame($this->admin->id, $message->user_id);
        $this->assertStringContainsString('Fichaje aprobado', $message->body);
        $this->assertStringContainsString('Rivaldo Nunes', $message->body);
        $this->assertStringContainsString('300.000', $message->body);
    }

    public function test_a_rejection_is_told_too_and_moves_nothing(): void
    {
        $proposal = $this->proposal([
            'type' => Transfer::TYPE_SALE,
            'player_id' => $this->mine->id,
            'fee' => 500_000,
        ]);

        app(TransferService::class)->reject($proposal, $this->admin);

        $message = Conversation::query()->first()->messages()->latest('id')->first();

        $this->assertStringContainsString('Venta rechazada', $message->body);
        $this->assertStringContainsString('El Mío', $message->body);
        $this->assertSame(Transfer::STATUS_REJECTED, $proposal->fresh()->status);
        $this->assertSame(1_000_000, $this->balance());
        $this->assertNull($this->mine->fresh()->left_at);
    }

    // ── Quién puede qué ───────────────────────────────────────────────────

    public function test_only_an_administrator_answers_and_only_once(): void
    {
        $proposal = $this->proposal([
            'type' => Transfer::TYPE_SALE,
            'player_id' => $this->mine->id,
            'fee' => 1,
        ]);

        try {
            app(TransferService::class)->approve($proposal, $this->coach, ['external_club' => 'Palmeiras', 'fee' => 1]);
            $this->fail('un técnico no debería poder firmar su propia propuesta');
        } catch (ValidationException) {
            $this->assertSame(Transfer::STATUS_PROPOSED, $proposal->fresh()->status);
        }

        app(TransferService::class)->approve($proposal->fresh(), $this->admin, ['external_club' => 'Palmeiras', 'fee' => 1]);

        $this->expectException(ValidationException::class);
        app(TransferService::class)->approve($proposal->fresh(), $this->admin, ['external_club' => 'Palmeiras', 'fee' => 1]);
    }

    public function test_the_tray_shows_what_is_waiting_and_closes_it(): void
    {
        $proposal = $this->proposal([
            'type' => Transfer::TYPE_SALE,
            'player_id' => $this->mine->id,
            'fee' => 500_000,
        ]);

        $this->actingAs($this->admin);

        $this->assertSame('1', TransferResource::getNavigationBadge());

        Livewire::test(ListTransfers::class)
            ->mountTableAction('approve', $proposal)
            ->setTableActionData(['external_club' => 'Palmeiras', 'fee' => 500_000])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(Transfer::STATUS_EXECUTED, $proposal->fresh()->status);
        $this->assertSame(1_500_000, $this->balance());
    }

    public function test_the_budget_takes_no_proposals(): void
    {
        $this->asCoach();

        $this->assertFalse(BudgetResource::canCreate());
        $this->get('/club/contabilidad/create')->assertNotFound();
        $this->assertTrue(Gate::forUser($this->coach)->allows('create', Transfer::class));
    }
}
