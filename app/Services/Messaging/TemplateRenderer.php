<?php

namespace App\Services\Messaging;

use App\Models\MessageTemplate;
use App\Models\Society;
use App\Support\MessageCatalogue;
use Illuminate\Support\Str;

/**
 * Turns a template and a bag of values into the words a resident reads.
 *
 * Deliberately not Blade. These templates are edited by committee members in
 * a textarea, and rendering user-edited Blade would mean running whatever they
 * typed. Substitution is a plain, total replacement of `{{ name }}` tokens:
 * the worst a mistake can do is leave a literal token in the text, which the
 * editor's preview shows before anything is sent.
 */
class TemplateRenderer
{
    /**
     * The society's own wording for a message, or the packaged default.
     *
     * @return array{subject: string, body: string, source: string}
     */
    public function resolve(Society $society, string $key, string $channel = 'email'): array
    {
        $default = MessageCatalogue::get($key);

        $custom = MessageTemplate::query()
            ->forSociety($society)
            ->forKey($key, $channel)
            ->where('is_active', true)
            ->first();

        if ($custom !== null) {
            return [
                'subject' => (string) ($custom->subject ?? $default['subject'] ?? ''),
                'body' => $custom->body,
                'source' => 'society',
            ];
        }

        return [
            'subject' => (string) ($default['subject'] ?? ''),
            'body' => (string) ($default['body'] ?? ''),
            'source' => 'default',
        ];
    }

    /**
     * Renders a message end to end.
     *
     * @param  array<string, mixed>  $data
     * @return array{subject: string, body: string, source: string, missing: array<int, string>}
     */
    public function render(Society $society, string $key, array $data, string $channel = 'email'): array
    {
        $template = $this->resolve($society, $key, $channel);

        $data += $this->societyValues($society);

        return [
            'subject' => $this->substitute($template['subject'], $data),
            'body' => $this->substitute($template['body'], $data),
            'source' => $template['source'],
            'missing' => $this->unknownTokens($template['body'].' '.$template['subject'], $key),
        ];
    }

    /**
     * Replaces every `{{ token }}` with its value.
     *
     * A token with no value becomes an empty string rather than being left in
     * place: half a sentence reads better than machinery showing through.
     * Whitespace inside the braces is tolerated because people type it.
     *
     * @param  array<string, mixed>  $data
     */
    public function substitute(string $text, array $data): string
    {
        $rendered = preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            fn (array $m) => (string) ($data[strtolower($m[1])] ?? ''),
            $text,
        ) ?? $text;

        // Removing a token can leave a line that was only that token, and a
        // run of blank lines reads as a mistake.
        return trim(preg_replace("/\n{3,}/", "\n\n", $rendered) ?? $rendered);
    }

    /**
     * Placeholders used in the text that the message does not actually supply
     * -- almost always a typo. Surfaced in the editor, never at send time.
     *
     * @return array<int, string>
     */
    public function unknownTokens(string $text, string $key): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $text, $matches);

        $known = array_keys(MessageCatalogue::placeholders($key));

        return array_values(array_unique(array_filter(
            array_map('strtolower', $matches[1] ?? []),
            fn (string $token) => ! in_array($token, $known, true),
        )));
    }

    /**
     * Sample values, so the editor can show a realistic preview rather than a
     * page of empty gaps.
     *
     * @return array<string, string>
     */
    public function sampleData(Society $society, string $key): array
    {
        $samples = [
            'invoice_number' => 'INV-2026-0042',
            'invoice_period' => 'April 2026',
            'amount_due' => '₹4,250.00',
            'amount_total' => '₹4,250.00',
            'due_date' => now()->addDays(3)->format('j F Y'),
            'due_phrase' => 'due in 3 days',
            'days_overdue' => '0',
            'late_fee_line' => '',
            'late_fee_total' => '₹0.00',
            'invoice_link' => url('/invoices/1'),
            'receipt_number' => 'RCPT-2026-0117',
            'amount_paid' => '₹4,250.00',
            'paid_on' => now()->format('j F Y'),
            'payment_method' => 'UPI',
            'reference' => '409218335571',
            'balance' => '₹0.00',
            'receipt_link' => url('/receipts/1'),
            'notice_title' => 'Water tank cleaning on Sunday',
            'notice_body' => 'The overhead tanks will be cleaned on Sunday morning. Supply will be off between 9am and 1pm.',
            'notice_category' => 'Maintenance',
            'published_on' => now()->format('j F Y'),
            'notice_link' => url('/notices/1'),
            'meeting_title' => 'Annual General Meeting 2026',
            'meeting_type' => 'Annual General Meeting',
            'meeting_date' => now()->addDays(14)->format('j F Y'),
            'meeting_time' => '6:30 pm',
            'meeting_venue' => 'Community hall, ground floor',
            'meeting_agenda' => "1. Adoption of the audited accounts\n2. Revision of the maintenance rate\n3. Lift replacement quotations",
            'meeting_link' => url('/meetings/1'),
            'attendee_count' => '48',
            'resolutions' => "1. Accounts adopted unanimously.\n2. Maintenance revised to ₹3.20 per sq ft from 1 July.",
            'ticket_number' => 'HD-2026-0311',
            'ticket_subject' => 'Lift in B wing is stopping between floors',
            'ticket_status' => 'In progress',
            'ticket_note' => 'The technician has been called and is expected tomorrow morning.',
            'assignee_name' => 'Ramesh (facility manager)',
            'ticket_link' => url('/helpdesk/1'),
            'visitor_name' => 'Amazon',
            'visitor_purpose' => 'Delivery',
            'visitor_company' => 'Amazon',
            'visitor_phone' => '98XXXXXX21',
            'visitor_link' => url('/visitors'),
        ];

        $allowed = MessageCatalogue::placeholders($key);

        return array_intersect_key($samples, $allowed)
            + ['resident_name' => 'Anita Shah', 'unit_label' => 'A-101']
            + $this->societyValues($society);
    }

    /** @return array<string, string> */
    private function societyValues(Society $society): array
    {
        return [
            'society_name' => $society->name,
            'today' => now()->format('j F Y'),
        ];
    }

    /** A short, human name for a template key. */
    public function label(string $key): string
    {
        return MessageCatalogue::get($key)['name'] ?? Str::headline($key);
    }
}
