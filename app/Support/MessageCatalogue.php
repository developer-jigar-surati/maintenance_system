<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The messages the system sends, and the wording it starts from.
 *
 * A society can rewrite any of these in Settings; what lives here is the
 * fallback, so a fresh installation communicates properly before anyone has
 * touched the templates. Each entry declares the placeholders it may use, and
 * that list is what the editor offers and what validation checks against --
 * a typo in a placeholder should be caught while writing, not discovered by a
 * resident receiving "{{ resdient_name }}".
 */
class MessageCatalogue
{
    public const PAYMENT_REMINDER = 'payment_reminder';

    public const PAYMENT_RECEIPT = 'payment_receipt';

    public const INVOICE_ISSUED = 'invoice_issued';

    public const NOTICE_PUBLISHED = 'notice_published';

    public const MEETING_NOTICE = 'meeting_notice';

    public const MEETING_MINUTES = 'meeting_minutes';

    public const COMPLAINT_UPDATE = 'complaint_update';

    public const VISITOR_WAITING = 'visitor_waiting';

    /** Placeholders every message may use, whatever it is about. */
    private const COMMON = [
        'society_name' => 'The society name',
        'resident_name' => 'Who the message is addressed to',
        'unit_label' => 'Flat or house number, e.g. A-101',
        'today' => "Today's date",
    ];

    /**
     * @return array<string, array{
     *     name: string, description: string, channels: array<int, string>,
     *     subject: string, body: string, placeholders: array<string, string>
     * }>
     */
    public static function all(): array
    {
        return [
            self::PAYMENT_REMINDER => [
                'name' => 'Maintenance reminder',
                'description' => 'Sent on each step of the reminder schedule for an unpaid bill.',
                'channels' => ['email', 'sms', 'whatsapp'],
                'subject' => 'Maintenance {{ due_phrase }} - {{ society_name }}',
                'body' => <<<'TXT'
                    Hello {{ resident_name }},

                    Your maintenance bill {{ invoice_number }} for {{ unit_label }} is {{ due_phrase }} ({{ due_date }}).

                    Amount outstanding: {{ amount_due }}
                    {{ late_fee_line }}

                    You can pay and download your receipt in the app: {{ invoice_link }}

                    If you have already paid, please ignore this message. - {{ society_name }}
                    TXT,
                'placeholders' => [
                    'invoice_number' => 'The bill number',
                    'invoice_period' => 'The period the bill covers',
                    'amount_due' => 'Balance still outstanding',
                    'amount_total' => 'The full billed amount',
                    'due_date' => 'When the bill is due',
                    'due_phrase' => '"due in 3 days", "due today" or "overdue by 21 days"',
                    'days_overdue' => 'Days past the due date, 0 if not yet due',
                    'late_fee_line' => 'A line about interest charged, empty when there is none',
                    'late_fee_total' => 'Interest charged so far',
                    'invoice_link' => 'Link to the bill',
                ] + self::COMMON,
            ],

            self::PAYMENT_RECEIPT => [
                'name' => 'Payment receipt',
                'description' => 'Sent when a payment is recorded, whether it was made online or at the desk.',
                'channels' => ['email', 'sms', 'whatsapp'],
                'subject' => 'Receipt {{ receipt_number }} - {{ society_name }}',
                'body' => <<<'TXT'
                    Hello {{ resident_name }},

                    We have received {{ amount_paid }} towards {{ unit_label }} on {{ paid_on }}.

                    Receipt number: {{ receipt_number }}
                    Paid by: {{ payment_method }}
                    Balance now outstanding: {{ balance }}

                    Your receipt: {{ receipt_link }}

                    Thank you. - {{ society_name }}
                    TXT,
                'placeholders' => [
                    'receipt_number' => 'The receipt number',
                    'amount_paid' => 'What was paid',
                    'paid_on' => 'Date of payment',
                    'payment_method' => 'Cash, UPI, cheque, bank transfer, online',
                    'reference' => 'Cheque or transaction reference',
                    'balance' => 'What is still outstanding afterwards',
                    'receipt_link' => 'Link to the receipt',
                ] + self::COMMON,
            ],

            self::INVOICE_ISSUED => [
                'name' => 'New bill raised',
                'description' => 'Sent when a billing run raises a new maintenance bill.',
                'channels' => ['email', 'sms', 'whatsapp'],
                'subject' => 'Maintenance bill for {{ invoice_period }} - {{ society_name }}',
                'body' => <<<'TXT'
                    Hello {{ resident_name }},

                    Your maintenance bill for {{ invoice_period }} is ready.

                    Bill number: {{ invoice_number }}
                    Amount: {{ amount_total }}
                    Due on: {{ due_date }}

                    View and pay: {{ invoice_link }} - {{ society_name }}
                    TXT,
                'placeholders' => [
                    'invoice_number' => 'The bill number',
                    'invoice_period' => 'The period the bill covers',
                    'amount_total' => 'The full billed amount',
                    'amount_due' => 'Balance outstanding',
                    'due_date' => 'When the bill is due',
                    'invoice_link' => 'Link to the bill',
                ] + self::COMMON,
            ],

            self::NOTICE_PUBLISHED => [
                'name' => 'Notice published',
                'description' => 'Sent when a circular or notice is published to residents.',
                'channels' => ['email', 'sms', 'whatsapp'],
                'subject' => '{{ notice_title }} - {{ society_name }}',
                'body' => <<<'TXT'
                    Hello {{ resident_name }},

                    {{ notice_title }}

                    {{ notice_body }}

                    Posted on {{ published_on }}. Read it in the app: {{ notice_link }} - {{ society_name }}
                    TXT,
                'placeholders' => [
                    'notice_title' => 'The notice heading',
                    'notice_body' => 'The full text of the notice',
                    'notice_category' => 'General, urgent, maintenance and so on',
                    'published_on' => 'When it was posted',
                    'notice_link' => 'Link to the notice',
                ] + self::COMMON,
            ],

            self::MEETING_NOTICE => [
                'name' => 'Meeting notice',
                'description' => 'The formal notice of a general body or committee meeting, with its agenda.',
                'channels' => ['email', 'whatsapp'],
                'subject' => 'Notice of {{ meeting_type }} on {{ meeting_date }} - {{ society_name }}',
                'body' => <<<'TXT'
                    Hello {{ resident_name }},

                    Notice is hereby given that a {{ meeting_type }} of {{ society_name }} will be held as follows.

                    Date:  {{ meeting_date }}
                    Time:  {{ meeting_time }}
                    Venue: {{ meeting_venue }}

                    Agenda:
                    {{ meeting_agenda }}

                    Members unable to attend may appoint a proxy in writing. Quorum requirements apply.

                    Details: {{ meeting_link }} - {{ society_name }}
                    TXT,
                'placeholders' => [
                    'meeting_title' => 'The meeting title',
                    'meeting_type' => 'AGM, SGM, committee meeting and so on',
                    'meeting_date' => 'The date of the meeting',
                    'meeting_time' => 'The starting time',
                    'meeting_venue' => 'Where it is held',
                    'meeting_agenda' => 'The agenda, one item per line',
                    'meeting_link' => 'Link to the meeting',
                ] + self::COMMON,
            ],

            self::MEETING_MINUTES => [
                'name' => 'Minutes circulated',
                'description' => 'Sent when the minutes of a meeting are finalised and circulated.',
                'channels' => ['email'],
                'subject' => 'Minutes of {{ meeting_title }} - {{ society_name }}',
                'body' => <<<'TXT'
                    Hello {{ resident_name }},

                    The minutes of {{ meeting_title }}, held on {{ meeting_date }}, have been circulated.

                    Present: {{ attendee_count }} members.

                    Resolutions passed:
                    {{ resolutions }}

                    Read the full minutes: {{ meeting_link }} - {{ society_name }}
                    TXT,
                'placeholders' => [
                    'meeting_title' => 'The meeting title',
                    'meeting_date' => 'When it was held',
                    'attendee_count' => 'How many members attended',
                    'resolutions' => 'Resolutions passed, one per line',
                    'meeting_link' => 'Link to the minutes',
                ] + self::COMMON,
            ],

            self::COMPLAINT_UPDATE => [
                'name' => 'Complaint update',
                'description' => 'Sent when a complaint is assigned, updated or resolved.',
                'channels' => ['email', 'sms', 'whatsapp'],
                'subject' => 'Complaint {{ ticket_number }} is now {{ ticket_status }}',
                'body' => <<<'TXT'
                    Hello {{ resident_name }},

                    Your complaint {{ ticket_number }} - {{ ticket_subject }} - is now {{ ticket_status }}.

                    {{ ticket_note }}

                    Follow it here: {{ ticket_link }} - {{ society_name }}
                    TXT,
                'placeholders' => [
                    'ticket_number' => 'The complaint reference',
                    'ticket_subject' => 'What the complaint is about',
                    'ticket_status' => 'Open, assigned, in progress, resolved, closed',
                    'ticket_note' => 'The latest note added by whoever is handling it',
                    'assignee_name' => 'Who is handling it',
                    'ticket_link' => 'Link to the complaint',
                ] + self::COMMON,
            ],

            self::VISITOR_WAITING => [
                'name' => 'Visitor at the gate',
                'description' => 'Sent to a resident when the guard logs a visitor for their home.',
                'channels' => ['sms', 'whatsapp', 'in_app'],
                'subject' => '{{ visitor_name }} is at the gate',
                'body' => <<<'TXT'
                    {{ visitor_name }} ({{ visitor_purpose }}) is at the gate for {{ unit_label }}.

                    Approve or decline in the app: {{ visitor_link }} - {{ society_name }}
                    TXT,
                'placeholders' => [
                    'visitor_name' => 'Who is at the gate',
                    'visitor_purpose' => 'Delivery, guest, cab, service and so on',
                    'visitor_company' => 'The company they are from, where known',
                    'visitor_phone' => 'Their phone number, where taken',
                    'visitor_link' => 'Link to approve or decline',
                ] + self::COMMON,
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /**
     * The placeholders a given message may use.
     *
     * @return array<string, string>
     */
    public static function placeholders(string $key): array
    {
        return self::all()[$key]['placeholders'] ?? self::COMMON;
    }

    public static function name(string $key): string
    {
        return self::all()[$key]['name'] ?? Str::headline($key);
    }
}
