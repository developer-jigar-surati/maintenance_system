<?php

namespace App\Livewire;

use App\Enums\Permission;
use App\Models\AmenityBooking;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Meeting;
use App\Models\Notice;
use App\Models\Poll;
use App\Models\VisitorLog;
use App\Services\Helpdesk\ComplaintService;
use App\Services\Reporting\FinancialReports;
use App\Support\SocietyContext;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The landing screen.
 *
 * A treasurer wants collection figures; a resident wants their own bill and
 * whether the lift is fixed. Rather than one dashboard with half its tiles
 * greyed out, the view is chosen from what the user is allowed to see.
 */
#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    /**
     * A guard has no use for a dashboard: their whole job is the gate. Send
     * them straight there rather than showing a screen of figures they cannot
     * act on and mostly cannot see.
     */
    public function mount()
    {
        if (auth()->user()->isGateOnly()) {
            return $this->redirect(route('gate.index'), navigate: true);
        }
    }

    public function render()
    {
        $society = app(SocietyContext::class)->check();
        $user = auth()->user();

        $isManagement = $user->isSuperAdmin() || $user->can(Permission::BILLING_MANAGE)
            || $user->can(Permission::REPORT_VIEW);

        return view('livewire.dashboard', [
            'society' => $society,
            'isManagement' => $isManagement,
            'data' => $isManagement
                ? $this->managementData($society)
                : $this->residentData($user),
        ])->title('Dashboard');
    }

    private function managementData($society): array
    {
        $reports = app(FinancialReports::class);

        return [
            'finance' => $reports->dashboard($society),
            'trend' => $reports->collectionTrend($society, 6),
            'topDefaulters' => $reports->defaulters($society)->take(5),
            'helpdesk' => app(ComplaintService::class)->statistics($society),
            'recentComplaints' => Complaint::query()
                ->open()
                ->with(['unit.block', 'category', 'assignee'])
                ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
                ->latest()
                ->take(5)
                ->get(),
            'upcomingMeetings' => Meeting::query()->upcoming()->take(3)->get(),
            'pendingBookings' => AmenityBooking::query()
                ->where('status', 'pending')
                ->with(['amenity', 'unit.block'])
                ->orderBy('starts_at')
                ->take(5)
                ->get(),
            'visitorsInside' => VisitorLog::query()->inside()->count(),
        ];
    }

    private function residentData($user): array
    {
        $unitIds = $user->units()->pluck('units.id');

        $invoices = Invoice::query()
            ->whereIn('unit_id', $unitIds)
            ->open()
            ->with('unit.block')
            ->orderBy('due_date')
            ->get();

        return [
            'units' => $user->units()->with('block')->get(),
            'outstanding' => round((float) $invoices->sum('balance'), 2),
            'overdueCount' => $invoices->filter(fn (Invoice $i) => $i->isOverdue())->count(),
            'openInvoices' => $invoices->take(5),
            'myComplaints' => Complaint::query()
                ->visibleTo($user)
                ->open()
                ->with(['category', 'unit.block'])
                ->latest()
                ->take(5)
                ->get(),
            'notices' => Notice::query()
                ->published()
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->take(5)
                ->get()
                ->filter(fn (Notice $n) => $n->isVisibleTo($user))
                ->take(3),
            'meetings' => Meeting::query()->upcoming()->take(2)->get(),
            'openPolls' => Poll::query()->open()->with('options')->take(3)->get(),
            'bookings' => AmenityBooking::query()
                ->whereIn('unit_id', $unitIds)
                ->upcoming()
                ->with('amenity')
                ->take(3)
                ->get(),
            'expectedVisitors' => VisitorLog::query()
                ->whereIn('unit_id', $unitIds)
                ->whereIn('status', ['expected', 'pending_approval'])
                ->orderBy('expected_at')
                ->take(5)
                ->get(),
        ];
    }
}
