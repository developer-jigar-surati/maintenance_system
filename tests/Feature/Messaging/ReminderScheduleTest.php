<?php

namespace Tests\Feature\Messaging;

use App\Models\Invoice;
use App\Models\MessageDispatch;
use App\Models\MessageTemplate;
use App\Models\ReminderRule;
use App\Models\Society;
use App\Models\Unit;
use App\Models\UnitResident;
use App\Services\Messaging\InvoiceReminders;
use App\Support\MessageCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /** A unit with a billing contact and one open bill due on the given date. */
    private function billDue(Society $society, string $dueDate, float $amount = 4000): Invoice
    {
        $unit = $this->makeUnit($society, ['unit_number' => '101']);
        $resident = $this->makeUser($society);

        UnitResident::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'user_id' => $resident->id,
            'relation' => 'owner',
            'is_primary' => true,
            'is_billing_contact' => true,
            'status' => 'active',
        ]);

        return Invoice::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'invoice_number' => 'INV-'.fake()->unique()->numberBetween(1000, 9999),
            'issue_date' => now()->subDays(5),
            'due_date' => $dueDate,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'subtotal' => $amount,
            'total' => $amount,
            'balance' => $amount,
            'status' => 'issued',
        ]);
    }

    public function test_a_new_society_starts_with_a_reminder_ladder(): void
    {
        $society = $this->makeSociety();

        $offsets = ReminderRule::forEvent('invoice_due')->orderBy('offset_days')->pluck('offset_days');

        $this->assertSame([-3, 0, 7, 21, 45], $offsets->all());
    }

    public function test_a_reminder_goes_out_only_on_a_day_the_schedule_names(): void
    {
        $society = $this->makeSociety();
        $invoice = $this->billDue($society, now()->addDays(10)->toDateString());

        $reminders = app(InvoiceReminders::class);

        // Ten days out matches no step in the default ladder.
        $this->assertSame(0, $reminders->run($society)['sent']);

        // Three days out does.
        $result = $reminders->run($society, $invoice->due_date->copy()->subDays(3));

        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, MessageDispatch::count());
    }

    public function test_running_twice_in_a_day_does_not_send_twice(): void
    {
        $society = $this->makeSociety();
        $invoice = $this->billDue($society, now()->addDays(3)->toDateString());

        $reminders = app(InvoiceReminders::class);

        $this->assertSame(1, $reminders->run($society)['sent']);
        $this->assertSame(0, $reminders->run($society)['sent']);
        $this->assertSame(1, MessageDispatch::count());
    }

    public function test_the_committee_can_change_when_reminders_go_out(): void
    {
        $society = $this->makeSociety();
        $invoice = $this->billDue($society, now()->addDays(10)->toDateString());

        // Nothing is scheduled ten days out until the committee says so.
        ReminderRule::create([
            'society_id' => $society->id,
            'event' => 'invoice_due',
            'offset_days' => -10,
            'label' => 'Early warning',
            'template_key' => MessageCatalogue::PAYMENT_REMINDER,
            'channels' => ['email'],
            'is_active' => true,
        ]);

        $this->assertSame(1, app(InvoiceReminders::class)->run($society)['sent']);
    }

    public function test_a_step_switched_off_sends_nothing(): void
    {
        $society = $this->makeSociety();
        $this->billDue($society, now()->addDays(3)->toDateString());

        ReminderRule::forEvent('invoice_due')->where('offset_days', -3)
            ->update(['is_active' => false]);

        $this->assertSame(0, app(InvoiceReminders::class)->run($society)['sent']);
    }

    public function test_a_society_writes_in_its_own_words_when_it_has_a_template(): void
    {
        $society = $this->makeSociety();
        $invoice = $this->billDue($society, now()->addDays(3)->toDateString(), 1500);

        MessageTemplate::create([
            'society_id' => $society->id,
            'key' => MessageCatalogue::PAYMENT_REMINDER,
            'channel' => 'email',
            'name' => 'Maintenance reminder',
            'subject' => 'Bill {{ invoice_number }}',
            'body' => '{{ resident_name }}, {{ unit_label }} owes {{ amount_due }}, {{ due_phrase }}.',
        ]);

        app(InvoiceReminders::class)->run($society);

        $dispatch = MessageDispatch::firstOrFail();

        $this->assertSame("Bill {$invoice->invoice_number}", $dispatch->subject);
        $this->assertStringContainsString('101 owes ₹1,500.00, due in 3 days.', $dispatch->body);
        $this->assertStringNotContainsString('{{', $dispatch->body);
    }

    public function test_a_bill_with_nobody_to_write_to_is_skipped_not_failed(): void
    {
        $society = $this->makeSociety();
        $unit = $this->makeUnit($society, ['unit_number' => '999']);

        Invoice::create([
            'society_id' => $society->id,
            'unit_id' => $unit->id,
            'invoice_number' => 'INV-0001',
            'issue_date' => now(),
            'due_date' => now()->addDays(3),
            'subtotal' => 100,
            'total' => 100,
            'balance' => 100,
            'status' => 'issued',
        ]);

        $result = app(InvoiceReminders::class)->run($society);

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped']);
    }

    public function test_a_paid_bill_is_never_chased(): void
    {
        $society = $this->makeSociety();
        $invoice = $this->billDue($society, now()->addDays(3)->toDateString());
        $invoice->forceFill(['status' => 'paid', 'balance' => 0])->save();

        $this->assertSame(0, app(InvoiceReminders::class)->run($society)['sent']);
    }

    public function test_one_society_reminder_schedule_does_not_reach_another(): void
    {
        $first = $this->makeSociety();
        $this->billDue($first, now()->addDays(3)->toDateString());

        $second = $this->makeSociety();
        ReminderRule::forSociety($second)->delete();

        $this->actingWithinSociety($first);

        $result = app(InvoiceReminders::class)->run($second);

        $this->assertSame(0, $result['rules']);
        $this->assertSame(0, $result['sent']);
    }
}
