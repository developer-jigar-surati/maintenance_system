<?php

namespace Tests\Feature\Messaging;

use App\Models\MessageTemplate;
use App\Services\Messaging\TemplateRenderer;
use App\Support\MessageCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_placeholders_are_replaced_whatever_spacing_is_typed(): void
    {
        $renderer = app(TemplateRenderer::class);

        $this->assertSame(
            'Hello Anita, flat A-101.',
            $renderer->substitute(
                'Hello {{ resident_name }}, flat {{unit_label}}.',
                ['resident_name' => 'Anita', 'unit_label' => 'A-101'],
            ),
        );
    }

    public function test_a_placeholder_with_no_value_leaves_no_machinery_showing(): void
    {
        $rendered = app(TemplateRenderer::class)
            ->substitute('Amount: {{ amount_due }}{{ late_fee_line }}', ['amount_due' => '₹100']);

        $this->assertSame('Amount: ₹100', $rendered);
    }

    public function test_a_template_is_never_executed_as_code(): void
    {
        // Committee members edit these in a textarea; a template that could
        // run what they typed would be a way into the server.
        $rendered = app(TemplateRenderer::class)->substitute(
            '{{ resident_name }} @php echo 1+1; @endphp {{ 7*7 }}',
            ['resident_name' => 'Anita'],
        );

        $this->assertStringContainsString('@php echo 1+1; @endphp', $rendered);
        $this->assertStringContainsString('{{ 7*7 }}', $rendered);
        $this->assertStringNotContainsString('49', $rendered);
    }

    public function test_the_packaged_wording_is_used_until_a_society_writes_its_own(): void
    {
        $society = $this->makeSociety();
        $renderer = app(TemplateRenderer::class);

        $this->assertSame('default', $renderer->resolve($society, MessageCatalogue::PAYMENT_REMINDER)['source']);

        MessageTemplate::create([
            'society_id' => $society->id,
            'key' => MessageCatalogue::PAYMENT_REMINDER,
            'channel' => 'email',
            'name' => 'Ours',
            'subject' => 'Pay up',
            'body' => 'Please pay {{ amount_due }}.',
        ]);

        $resolved = $renderer->resolve($society, MessageCatalogue::PAYMENT_REMINDER);

        $this->assertSame('society', $resolved['source']);
        $this->assertSame('Pay up', $resolved['subject']);
    }

    public function test_deleting_the_override_restores_the_standard_wording(): void
    {
        $society = $this->makeSociety();

        MessageTemplate::create([
            'society_id' => $society->id,
            'key' => MessageCatalogue::PAYMENT_REMINDER,
            'channel' => 'email',
            'name' => 'Ours',
            'body' => 'Pay.',
        ])->delete();

        $this->assertSame(
            'default',
            app(TemplateRenderer::class)->resolve($society, MessageCatalogue::PAYMENT_REMINDER)['source'],
        );
    }

    public function test_a_mistyped_placeholder_is_reported_rather_than_sent(): void
    {
        $unknown = app(TemplateRenderer::class)->unknownTokens(
            'Hello {{ resdient_name }}, you owe {{ amount_due }}.',
            MessageCatalogue::PAYMENT_REMINDER,
        );

        $this->assertSame(['resdient_name'], $unknown);
    }

    public function test_every_packaged_template_only_uses_placeholders_it_declares(): void
    {
        $society = $this->makeSociety();
        $renderer = app(TemplateRenderer::class);

        foreach (MessageCatalogue::keys() as $key) {
            $template = $renderer->resolve($society, $key);

            $this->assertSame(
                [],
                $renderer->unknownTokens($template['body'].' '.$template['subject'], $key),
                "The packaged '{$key}' template uses a placeholder it does not declare.",
            );
        }
    }

    public function test_the_preview_fills_every_placeholder_a_template_uses(): void
    {
        $society = $this->makeSociety();
        $renderer = app(TemplateRenderer::class);

        foreach (MessageCatalogue::keys() as $key) {
            $template = $renderer->resolve($society, $key);
            $preview = $renderer->substitute($template['body'], $renderer->sampleData($society, $key));

            // late_fee_line is deliberately empty in the sample: no interest.
            $this->assertStringNotContainsString('{{', $preview, "The '{$key}' preview has gaps.");
            $this->assertNotSame('', trim($preview));
        }
    }
}
