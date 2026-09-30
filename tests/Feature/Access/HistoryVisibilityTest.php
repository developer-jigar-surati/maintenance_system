<?php

namespace Tests\Feature\Access;

use App\Enums\Role;
use App\Livewire\Property\ResidentIndex;
use App\Livewire\Property\UnitShow;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Society;
use App\Models\Unit;
use App\Models\UnitResident;
use App\Services\Property\Occupancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Who used to live in a flat, why they left, and how a neighbour voted are the
 * society's record, not a neighbour's business. These hold the line.
 */
class HistoryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /** A unit with one current resident and one who left. */
    private function unitWithAPast(Society $society): Unit
    {
        $unit = $this->makeUnit($society, ['unit_number' => '101']);
        $occupancy = app(Occupancy::class);

        $gone = $this->makeUser($society, Role::TENANT, ['name' => 'Departed Tenant']);
        $past = $occupancy->moveIn($unit, $gone, ['relation' => 'tenant', 'start_date' => '2022-01-01']);
        $occupancy->moveOut($past, Carbon::parse('2024-06-30'), 'Asked to leave');

        $staying = $this->makeUser($society, Role::OWNER, ['name' => 'Current Owner']);
        $occupancy->moveIn($unit, $staying, ['relation' => 'owner', 'is_billing_contact' => true]);

        return $unit->fresh();
    }

    public function test_the_office_bearers_can_read_a_units_past(): void
    {
        $society = $this->makeSociety();
        $unit = $this->unitWithAPast($society);

        foreach ([Role::SOCIETY_ADMIN, Role::PRESIDENT, Role::SECRETARY] as $role) {
            $officer = $this->makeUser($society, $role);

            // The screen opens on who lives here now; the past is one click
            // away, which is offered only to someone allowed to see it.
            $this->actingAs($officer)
                ->get(route('units.show', $unit))
                ->assertOk()
                ->assertSee('Show 1 past resident');

            Livewire::actingAs($officer)
                ->test(UnitShow::class, ['unit' => $unit])
                ->set('showPastResidents', true)
                ->assertSee('Departed Tenant')
                ->assertSee('Asked to leave');
        }
    }

    public function test_a_manager_sees_who_lives_there_now_and_nothing_further_back(): void
    {
        $society = $this->makeSociety();
        $unit = $this->unitWithAPast($society);

        // A manager runs the place day to day but is not an office bearer.
        $manager = $this->makeUser($society, Role::MANAGER);

        $response = $this->actingAs($manager)->get(route('units.show', $unit));

        $response->assertOk();
        $response->assertSee('Current Owner');
        $response->assertDontSee('Departed Tenant');
        $response->assertDontSee('Asked to leave');
        $response->assertDontSee('past resident');
        $response->assertSee('ask the secretary', false);
    }

    public function test_a_treasurer_and_a_committee_member_cannot_read_a_units_past(): void
    {
        $society = $this->makeSociety();
        $unit = $this->unitWithAPast($society);

        foreach ([Role::TREASURER, Role::COMMITTEE_MEMBER] as $role) {
            $user = $this->makeUser($society, $role);

            $response = $this->actingAs($user)->get(route('units.show', $unit));

            $response->assertOk();
            $response->assertDontSee('Departed Tenant');
        }
    }

    public function test_a_past_resident_is_never_sent_to_the_browser_at_all(): void
    {
        // Hiding a name behind an @if still ships it in the HTML, where
        // anyone can read it. It has to be filtered in the query.
        $society = $this->makeSociety();
        $unit = $this->unitWithAPast($society);

        $manager = $this->makeUser($society, Role::MANAGER);

        $body = $this->actingAs($manager)->get(route('units.show', $unit))->getContent();

        $this->assertStringNotContainsString('Departed Tenant', $body);
        $this->assertStringNotContainsString('Asked to leave', $body);
    }

    public function test_the_residents_list_hides_past_residencies_from_anyone_without_the_permission(): void
    {
        $society = $this->makeSociety();
        $this->unitWithAPast($society);

        $manager = $this->makeUser($society, Role::MANAGER);

        // Even asked for directly through the URL.
        $response = $this->actingAs($manager)->get(route('residents.index', ['status' => 'ended']));

        $response->assertOk();
        $response->assertDontSee('Departed Tenant');
        $response->assertSee('Current Owner');
    }

    public function test_the_secretary_can_filter_the_residents_list_down_to_past_residencies(): void
    {
        $society = $this->makeSociety();
        $this->unitWithAPast($society);

        $secretary = $this->makeUser($society, Role::SECRETARY);

        $response = $this->actingAs($secretary)->get(route('residents.index', ['status' => 'ended']));

        $response->assertOk();
        $response->assertSee('Departed Tenant');
    }

    public function test_asking_for_one_persons_history_without_the_permission_is_refused(): void
    {
        $society = $this->makeSociety();
        $unit = $this->unitWithAPast($society);

        $subject = UnitResident::where('unit_id', $unit->id)->firstOrFail();
        $manager = $this->makeUser($society, Role::MANAGER);

        Livewire::actingAs($manager)
            ->test(ResidentIndex::class)
            ->call('showHistory', $subject->user_id)
            ->assertForbidden();
    }

    public function test_switching_on_past_residents_without_the_permission_is_refused(): void
    {
        $society = $this->makeSociety();
        $unit = $this->unitWithAPast($society);

        Livewire::actingAs($this->makeUser($society, Role::MANAGER))
            ->test(UnitShow::class, ['unit' => $unit])
            ->set('showPastResidents', true)
            ->assertForbidden();
    }

    // --- votes -------------------------------------------------------------

    private function pollWithAVote(Society $society, bool $anonymous): Poll
    {
        $poll = Poll::create([
            'society_id' => $society->id,
            'title' => 'Replace the lift',
            'type' => 'single_choice',
            'voting_basis' => 'per_unit',
            'eligibility' => 'all_members',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_anonymous' => $anonymous,
            'show_results_before_close' => true,
            'status' => 'open',
        ]);

        $option = PollOption::create([
            'poll_id' => $poll->id,
            'label' => 'In favour',
            'sort_order' => 1,
        ]);

        $unit = $this->makeUnit($society, ['unit_number' => '303']);
        $voter = $this->makeUser($society, Role::OWNER, ['name' => 'Nosy Neighbour']);

        PollVote::create([
            'society_id' => $society->id,
            'poll_id' => $poll->id,
            'poll_option_id' => $option->id,
            'user_id' => $voter->id,
            'unit_id' => $unit->id,
            'weight' => 1,
            'voted_at' => now(),
        ]);

        return $poll->fresh();
    }

    public function test_the_secretary_can_see_who_voted_which_way(): void
    {
        $society = $this->makeSociety();
        $poll = $this->pollWithAVote($society, anonymous: false);

        $response = $this->actingAs($this->makeUser($society, Role::SECRETARY))
            ->get(route('polls.show', $poll));

        $response->assertOk();
        $response->assertSee('Nosy Neighbour');
    }

    public function test_a_resident_cannot_see_how_a_neighbour_voted(): void
    {
        $society = $this->makeSociety();
        $poll = $this->pollWithAVote($society, anonymous: false);

        foreach ([Role::OWNER, Role::TENANT, Role::COMMITTEE_MEMBER] as $role) {
            $response = $this->actingAs($this->makeUser($society, $role))
                ->get(route('polls.show', $poll));

            $response->assertOk();
            $response->assertDontSee('Nosy Neighbour');
        }
    }

    public function test_a_secret_ballot_stays_secret_from_the_office_bearers_too(): void
    {
        // A permission does not override what the society promised when it
        // set the poll up as anonymous.
        $society = $this->makeSociety();
        $poll = $this->pollWithAVote($society, anonymous: true);

        $response = $this->actingAs($this->makeUser($society, Role::SECRETARY))
            ->get(route('polls.show', $poll));

        $response->assertOk();
        $response->assertDontSee('Nosy Neighbour');
        $response->assertSee('secret ballot');
    }
}
