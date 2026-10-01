<?php

namespace Tests\Feature\Tenancy;

use App\Models\Invoice;
use App\Models\Unit;
use App\Support\SocietyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tenancy boundary. If any of these fail, one society can see another's
 * data, which is the single worst thing this system could do.
 */
class SocietyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_query_only_returns_rows_from_the_active_society(): void
    {
        $first = $this->makeSociety(['name' => 'Alpha Heights']);
        $this->makeUnit($first, ['unit_number' => '101']);
        $this->makeUnit($first, ['unit_number' => '102']);

        $second = $this->makeSociety(['name' => 'Beta Gardens']);
        $this->makeUnit($second, ['unit_number' => '201']);

        $this->actingWithinSociety($first);
        $this->assertSame(['101', '102'], Unit::orderBy('unit_number')->pluck('unit_number')->all());

        $this->actingWithinSociety($second);
        $this->assertSame(['201'], Unit::pluck('unit_number')->all());
    }

    public function test_society_id_is_stamped_on_create_without_being_passed(): void
    {
        $society = $this->makeSociety();

        $unit = Unit::create(['unit_number' => '303', 'type' => 'flat']);

        $this->assertSame($society->id, $unit->society_id);
    }

    public function test_an_explicit_society_id_is_respected(): void
    {
        $first = $this->makeSociety();
        $second = $this->makeSociety();

        // Still acting inside $second, but writing for $first.
        $unit = Unit::create([
            'society_id' => $first->id,
            'unit_number' => '404',
            'type' => 'flat',
        ]);

        $this->assertSame($first->id, $unit->society_id);
    }

    public function test_scoping_can_be_suspended_for_platform_wide_reporting(): void
    {
        $first = $this->makeSociety();
        $this->makeUnit($first);

        $second = $this->makeSociety();
        $this->makeUnit($second);

        $this->assertSame(1, Unit::count());

        $total = app(SocietyContext::class)->withoutScope(fn () => Unit::count());

        $this->assertSame(2, $total);
    }

    public function test_scoping_is_restored_after_a_suspended_block(): void
    {
        $society = $this->makeSociety();
        $this->makeUnit($society);
        $this->makeSociety();
        $this->makeUnit($society, ['unit_number' => '999']);

        app(SocietyContext::class)->withoutScope(fn () => Unit::count());

        // Back inside the second society, which owns no units of its own.
        $this->assertSame(0, Unit::count());
    }

    public function test_invoices_of_another_society_are_not_visible(): void
    {
        $first = $this->makeSociety();
        $unit = $this->makeUnit($first);

        Invoice::create([
            'society_id' => $first->id,
            'unit_id' => $unit->id,
            'invoice_number' => 'TEST/INV/1',
            'issue_date' => now(),
            'due_date' => now()->addDays(15),
            'total' => 1000,
            'balance' => 1000,
            'status' => 'issued',
        ]);

        $this->actingWithinSociety($this->makeSociety());

        $this->assertSame(0, Invoice::count());
        $this->assertNull(Invoice::where('invoice_number', 'TEST/INV/1')->first());
    }
}
