<?php

namespace Tests\Feature\Help;

use App\Support\ModuleGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleGuideTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, string> */
    private function routeNames(): array
    {
        return collect(Route::getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * A guide keyed on a route that no longer exists shows nowhere and is
     * found by nobody. This is how `helpdesk.index` sat unreachable while the
     * route was really called `complaints.index`.
     */
    public function test_every_guide_points_at_a_route_that_exists(): void
    {
        $routes = $this->routeNames();

        foreach (array_keys(ModuleGuide::all()) as $key) {
            $this->assertContains($key, $routes, "The guide for [{$key}] names a route that does not exist.");
        }
    }

    public function test_every_screen_a_committee_uses_explains_itself(): void
    {
        // Screens people land on and have to make sense of. Sign-in and
        // machinery are deliberately not in the list.
        $screens = [
            'dashboard', 'invoices.index', 'payments.index', 'receipts.index',
            'charge-heads.index', 'billing-plans.index', 'expenses.index',
            'vendors.index', 'reports.index', 'units.index', 'site-plan.index',
            'residents.index', 'directory.index', 'parking.index',
            'complaints.index', 'work-orders.index', 'assets.index',
            'amenities.index', 'staff.index', 'gate.index', 'visitors.index',
            'gate-passes.index', 'notices.index', 'meetings.index', 'polls.index',
            'documents.index', 'settings.index', 'settings.communication',
            'settings.roles', 'audit.index',
        ];

        $guides = ModuleGuide::all();

        foreach ($screens as $screen) {
            $this->assertArrayHasKey($screen, $guides, "[{$screen}] has no help.");
        }
    }

    public function test_a_guide_says_what_the_screen_is_for_before_anything_else(): void
    {
        foreach (ModuleGuide::all() as $key => $guide) {
            $this->assertArrayHasKey('title', $guide, "[{$key}] has no title.");
            $this->assertArrayHasKey('summary', $guide, "[{$key}] has no summary.");
            $this->assertArrayHasKey('steps', $guide);
            $this->assertArrayHasKey('watch', $guide);

            $this->assertNotSame('', trim($guide['summary']), "[{$key}] has an empty summary.");

            // Long enough to say something, short enough to be read.
            $this->assertLessThanOrEqual(200, mb_strlen($guide['summary']),
                "The summary for [{$key}] is too long to be read at a glance.");

            foreach ($guide['steps'] as $step) {
                $this->assertNotSame('', trim($step), "[{$key}] has an empty step.");
            }
        }
    }

    public function test_a_detail_screen_falls_back_to_its_lists_guide(): void
    {
        // Someone reading one bill needs the same explanation as someone
        // reading the list, not a blank panel.
        foreach (['invoices.show' => 'Invoices', 'meetings.show' => 'Meetings'] as $route => $title) {
            $resolved = ModuleGuide::for($route);

            $this->assertNotNull($resolved, "[{$route}] found no guide.");
            $this->assertSame($title, $resolved['title']);
        }
    }

    public function test_a_screen_with_no_guide_shows_no_panel_rather_than_an_empty_one(): void
    {
        $this->assertNull(ModuleGuide::for('login'));
        $this->assertNull(ModuleGuide::for('password.request'));
    }
}
