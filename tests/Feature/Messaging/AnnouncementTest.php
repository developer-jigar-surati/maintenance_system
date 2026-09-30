<?php

namespace Tests\Feature\Messaging;

use App\Enums\Role;
use App\Models\MessageDispatch;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\Society;
use App\Models\Unit;
use App\Models\UnitResident;
use App\Models\User;
use App\Services\Helpdesk\ComplaintService;
use App\Services\Messaging\Announcer;
use App\Services\Payments\ReceiptIssuer;
use App\Services\Security\GateService;
use App\Support\MessageCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every message a committee can edit in Settings needs something that actually
 * sends it. These cover the other half of that promise.
 */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    private function residentOf(Society $society, Unit $unit): User
    {
        $user = $this->makeUser($society);

        UnitResident::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'user_id' => $user->id,
            'relation' => 'owner',
            'is_primary' => true,
            'is_billing_contact' => true,
            'status' => 'active',
        ]);

        return $user;
    }

    public function test_a_receipt_reaches_the_resident_who_paid(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society, ['unit_number' => '101']);
        $resident = $this->residentOf($society, $unit);

        $payment = Payment::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'payment_number' => 'PAY-0001',
            'amount' => 2500,
            'method' => 'upi',
            'paid_at' => now(),
            'status' => 'completed',
            'payer_user_id' => $resident->id,
        ]);

        app(ReceiptIssuer::class)->issueFor($payment);

        $dispatch = MessageDispatch::where('template_key', MessageCatalogue::PAYMENT_RECEIPT)->firstOrFail();

        $this->assertSame($resident->id, $dispatch->user_id);
        $this->assertStringContainsString('₹2,500.00', $dispatch->body);
        $this->assertStringNotContainsString('{{', $dispatch->body);
    }

    public function test_a_receipt_is_not_sent_twice_for_the_same_payment(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society, ['unit_number' => '102']);
        $this->residentOf($society, $unit);

        $payment = Payment::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'payment_number' => 'PAY-0002',
            'amount' => 100,
            'method' => 'cash',
            'paid_at' => now(),
            'status' => 'completed',
        ]);

        $issuer = app(ReceiptIssuer::class);
        $issuer->issueFor($payment);
        $issuer->issueFor($payment->fresh());

        $this->assertSame(1, MessageDispatch::where('template_key', MessageCatalogue::PAYMENT_RECEIPT)->count());
    }

    public function test_a_notice_reaches_only_the_audience_it_names(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society, ['unit_number' => '201']);
        $tenant = $this->makeUser($society, Role::TENANT);

        UnitResident::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'user_id' => $tenant->id,
            'relation' => 'tenant',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $other = $this->makeUser($society);

        $notice = Notice::create([
            'society_id' => $society->id,
            'title' => 'Lift shutdown',
            'body' => 'The lift will be off on Sunday.',
            'audience' => 'tenants',
            'status' => 'published',
            'published_at' => now(),
        ]);

        app(Announcer::class)->noticePublished($notice);

        $recipients = MessageDispatch::where('template_key', MessageCatalogue::NOTICE_PUBLISHED)
            ->pluck('user_id');

        $this->assertTrue($recipients->contains($tenant->id));
        $this->assertFalse($recipients->contains($other->id));
    }

    public function test_a_resident_is_told_when_a_visitor_arrives_for_their_home(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society, ['unit_number' => '301']);
        $resident = $this->residentOf($society, $unit);
        $guard = $this->makeUser($society, Role::SECURITY_GUARD);

        app(GateService::class)->logArrival($society, [
            'visitor_name' => 'Amazon',
            'purpose' => 'delivery',
            'unit_id' => $unit->id,
        ], $guard);

        $dispatch = MessageDispatch::where('template_key', MessageCatalogue::VISITOR_WAITING)->firstOrFail();

        $this->assertSame($resident->id, $dispatch->user_id);
        $this->assertStringContainsString('Amazon', $dispatch->body);
    }

    public function test_a_delivery_left_at_the_gate_troubles_nobody(): void
    {
        $society = $this->makeSociety();
        $guard = $this->makeUser($society, Role::SECURITY_GUARD);

        app(GateService::class)->logArrival($society, [
            'visitor_name' => 'Swiggy',
            'purpose' => 'delivery',
            'unit_id' => null,
        ], $guard);

        $this->assertSame(0, MessageDispatch::where('template_key', MessageCatalogue::VISITOR_WAITING)->count());
    }

    public function test_a_complaint_moving_on_is_announced_once_per_change(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society, ['unit_number' => '401']);
        $resident = $this->residentOf($society, $unit);

        $complaint = app(ComplaintService::class)->raise($society, [
            'title' => 'Lift stuck between floors',
            'description' => 'It stops halfway.',
            'unit_id' => $unit->id,
        ], $resident);

        $service = app(ComplaintService::class);
        $service->changeStatus($complaint, 'in_progress');
        $service->changeStatus($complaint->fresh(), 'in_progress');
        $service->changeStatus($complaint->fresh(), 'resolved', null, 'The technician replaced the door sensor.');

        $bodies = MessageDispatch::where('template_key', MessageCatalogue::COMPLAINT_UPDATE)
            ->orderBy('id')
            ->pluck('body');

        $this->assertCount(2, $bodies);
        $this->assertStringContainsString('In progress', $bodies[0]);
        $this->assertStringContainsString('replaced the door sensor', $bodies[1]);
    }

    public function test_announcements_never_cross_from_one_society_into_another(): void
    {
        $first = $this->makeSociety();
        $firstUnit = $this->makeUnit($first, ['unit_number' => '501']);
        $this->residentOf($first, $firstUnit);

        $second = $this->makeSociety();
        $secondResident = $this->makeUser($second);

        $this->actingWithinSociety($first);

        $notice = Notice::create([
            'society_id' => $first->id,
            'title' => 'Water supply',
            'body' => 'Off between 9 and 1.',
            'audience' => 'all',
            'status' => 'published',
            'published_at' => now(),
        ]);

        app(Announcer::class)->noticePublished($notice);

        $recipients = MessageDispatch::acrossSocieties()
            ->where('template_key', MessageCatalogue::NOTICE_PUBLISHED)
            ->pluck('user_id');

        $this->assertFalse($recipients->contains($secondResident->id));
        $this->assertSame(
            [$first->id],
            MessageDispatch::acrossSocieties()->distinct()->pluck('society_id')->all(),
        );
    }
}
