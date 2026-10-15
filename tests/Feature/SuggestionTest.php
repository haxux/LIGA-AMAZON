<?php

namespace Tests\Feature;

use App\Filament\Club\Resources\Suggestions\Pages\CreateSuggestion;
use App\Filament\Club\Resources\Suggestions\Pages\ListSuggestions as CoachList;
use App\Filament\Resources\Suggestions\Pages\ListSuggestions as AdminList;
use App\Filament\Resources\Suggestions\SuggestionResource as AdminResource;
use App\Models\Club;
use App\Models\Suggestion;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El buzón de sugerencias: cualquier técnico manda ideas y sólo ve las suyas;
 * el administrador las lee todas y las marca.
 */
class SuggestionTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private User $other;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coach = User::factory()->coachOf(Club::factory()->create(['name' => 'Manaos FC']))->create(['name' => 'Técnico Uno']);
        $this->other = User::factory()->coachOf(Club::factory()->create(['name' => 'Tapajós SC']))->create(['name' => 'Técnico Dos']);
        $this->admin = User::factory()->create(['name' => 'Presidenta']);
    }

    private function asCoach(?User $coach = null): void
    {
        $this->actingAs($coach ?? $this->coach, 'club');
        Filament::setCurrentPanel('club');
    }

    public function test_a_coach_sends_a_suggestion(): void
    {
        $this->asCoach();

        Livewire::test(CreateSuggestion::class)
            ->fillForm(['title' => 'Un modo oscuro', 'body' => 'Que el sitio tenga un modo oscuro para ver los partidos de noche.'])
            ->call('create')
            ->assertHasNoFormErrors();

        $suggestion = Suggestion::sole();

        $this->assertSame($this->coach->id, $suggestion->user_id);
        $this->assertSame(Suggestion::STATUS_NEW, $suggestion->status);
        $this->assertSame('Un modo oscuro', $suggestion->title);
    }

    public function test_title_and_text_are_required(): void
    {
        $this->asCoach();

        Livewire::test(CreateSuggestion::class)
            ->fillForm(['title' => '', 'body' => ''])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required', 'body' => 'required']);

        $this->assertSame(0, Suggestion::count());
    }

    public function test_a_coach_only_sees_their_own_suggestions(): void
    {
        $mine = Suggestion::factory()->create(['user_id' => $this->coach->id]);
        $theirs = Suggestion::factory()->create(['user_id' => $this->other->id]);

        $this->asCoach();

        Livewire::test(CoachList::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_a_coach_cannot_read_or_change_anothers_suggestion(): void
    {
        $theirs = Suggestion::factory()->create(['user_id' => $this->other->id]);

        $this->assertFalse(Gate::forUser($this->coach)->allows('view', $theirs));
        $this->assertFalse(Gate::forUser($this->coach)->allows('update', $theirs));
        $this->assertFalse(Gate::forUser($this->coach)->allows('delete', $theirs));
        $this->assertTrue(Gate::forUser($this->coach)->allows('create', Suggestion::class));
        $this->assertTrue(Gate::forUser($this->admin)->allows('view', $theirs));
    }

    public function test_the_administrator_reads_all_and_marks_them(): void
    {
        $one = Suggestion::factory()->create(['user_id' => $this->coach->id]);
        $two = Suggestion::factory()->create(['user_id' => $this->other->id]);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        $this->assertSame('2', AdminResource::getNavigationBadge());

        Livewire::test(AdminList::class)
            ->assertCanSeeTableRecords([$one, $two])
            ->callTableAction('read', $one)
            ->callTableAction('done', $two);

        $this->assertSame(Suggestion::STATUS_READ, $one->fresh()->status);
        $this->assertSame(Suggestion::STATUS_DONE, $two->fresh()->status);
        $this->assertNull(AdminResource::getNavigationBadge());
    }

    public function test_the_administrator_opens_the_full_text(): void
    {
        $one = Suggestion::factory()->create(['user_id' => $this->coach->id, 'title' => 'Modo oscuro', 'body' => 'Un texto largo con la idea completa.']);

        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(AdminList::class)
            ->mountTableAction('view', $one)
            ->assertTableActionDataSet(['title' => 'Modo oscuro', 'body' => 'Un texto largo con la idea completa.']);
    }

    public function test_a_made_up_status_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        Suggestion::factory()->create(['status' => 'inventado']);
    }

    public function test_the_administrator_does_not_write_suggestions(): void
    {
        $this->assertFalse(AdminResource::canCreate());
        $this->assertFalse(Gate::forUser($this->admin)->allows('create', Suggestion::class));
    }
}
