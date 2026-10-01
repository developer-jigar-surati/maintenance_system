<?php

namespace App\Livewire\Facilities;

use App\Enums\Permission;
use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\Unit;
use App\Services\Facilities\AmenityBookingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class AmenityIndex extends Component
{
    public ?int $bookingAmenityId = null;

    public string $date = '';

    public string $startTime = '';

    public string $endTime = '';

    public int $guests = 0;

    public string $purpose = '';

    public ?int $unitId = null;

    /** Validation messages from the booking rules, shown in the modal. */
    public array $bookingErrors = [];

    public function mount(): void
    {
        $this->date = now()->addDay()->toDateString();
        $this->unitId = auth()->user()->units()->value('units.id');
    }

    public function startBooking(int $amenityId): void
    {
        $this->bookingAmenityId = $amenityId;
        $this->bookingErrors = [];
        $this->reset(['startTime', 'endTime', 'guests', 'purpose']);
        $this->dispatch('open-modal', 'book-amenity');
    }

    public function book(AmenityBookingService $bookings): void
    {
        Gate::authorize(Permission::AMENITY_BOOK);

        $this->validate([
            'date' => 'required|date',
            'startTime' => 'required|date_format:H:i',
            'endTime' => 'required|date_format:H:i|after:startTime',
            'guests' => 'integer|min:0|max:2000',
            'purpose' => 'nullable|string|max:200',
            'unitId' => 'required|exists:units,id',
        ]);

        $amenity = Amenity::findOrFail($this->bookingAmenityId);
        $unit = Unit::findOrFail($this->unitId);

        $start = Carbon::parse("{$this->date} {$this->startTime}");
        $end = Carbon::parse("{$this->date} {$this->endTime}");

        // Surface every rule that fails at once, rather than one at a time.
        $this->bookingErrors = $bookings->validate($amenity, $unit, $start, $end, $this->guests);

        if ($this->bookingErrors !== []) {
            return;
        }

        try {
            $booking = $bookings->book($amenity, $unit, $start, $end, auth()->user(), [
                'guests_count' => $this->guests,
                'purpose' => $this->purpose ?: null,
            ]);
        } catch (\DomainException $e) {
            $this->bookingErrors = [$e->getMessage()];

            return;
        }

        $this->dispatch('close-modal', 'book-amenity');
        $this->dispatch('notify',
            message: $booking->status === 'approved'
                ? "Booked - {$booking->booking_number}."
                : "Request {$booking->booking_number} sent for approval.",
            tone: 'positive');
    }

    public function approve(int $bookingId, AmenityBookingService $bookings): void
    {
        Gate::authorize(Permission::AMENITY_MANAGE);

        $bookings->approve(AmenityBooking::findOrFail($bookingId), auth()->user());
        $this->dispatch('notify', message: 'Booking approved.', tone: 'positive');
    }

    public function cancel(int $bookingId, AmenityBookingService $bookings): void
    {
        $booking = AmenityBooking::findOrFail($bookingId);

        abort_unless(
            $booking->booked_by === auth()->id() || auth()->user()->can(Permission::AMENITY_MANAGE),
            403
        );

        try {
            $bookings->cancel($booking, 'Cancelled by '.auth()->user()->name);
        } catch (\DomainException $e) {
            $this->dispatch('notify', message: $e->getMessage(), tone: 'critical');

            return;
        }

        $this->dispatch('notify', message: 'Booking cancelled.', tone: 'positive');
    }

    public function render()
    {
        $user = auth()->user();
        $canManage = $user->can(Permission::AMENITY_MANAGE);
        $unitIds = $user->units()->pluck('units.id');

        return view('livewire.facilities.amenity-index', [
            'amenities' => Amenity::bookable()->orderBy('name')->get(),
            'myBookings' => AmenityBooking::query()
                ->whereIn('unit_id', $unitIds)
                ->upcoming()
                ->with('amenity')
                ->get(),
            'pendingBookings' => $canManage
                ? AmenityBooking::query()
                    ->where('status', 'pending')
                    ->with(['amenity', 'unit.block', 'bookedBy'])
                    ->orderBy('starts_at')
                    ->get()
                : collect(),
            'canManage' => $canManage,
            'myUnits' => $user->units()->with('block')->get(),
            'selected' => $this->bookingAmenityId ? Amenity::find($this->bookingAmenityId) : null,
        ])->title('Amenities');
    }
}
