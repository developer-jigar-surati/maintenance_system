<?php

namespace App\Livewire\Settings;

use App\Enums\Permission;
use App\Models\PaymentGateway;
use App\Services\Payments\Gateways\GatewayManager;
use App\Support\SocietyContext;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Society profile, preferences and payment configuration.
 *
 * The payment section is where a society moves from offline to online: adding
 * and activating a gateway is what unlocks online collection for residents.
 */
#[Layout('components.layouts.app')]
class SocietySettings extends Component
{
    public array $profile = [];

    public array $preferences = [];

    /** Gateway form. */
    public string $provider = 'razorpay';

    public string $environment = 'test';

    public string $keyId = '';

    public string $keySecret = '';

    public string $webhookSecret = '';

    public function mount(): void
    {
        $society = app(SocietyContext::class)->check();

        $this->profile = [
            'name' => $society->name,
            'type' => $society->type,
            'registration_number' => $society->registration_number,
            'gstin' => $society->gstin,
            'address_line1' => $society->address_line1,
            'city' => $society->city,
            'state' => $society->state,
            'postal_code' => $society->postal_code,
            'contact_email' => $society->contact_email,
            'contact_phone' => $society->contact_phone,
            'area_unit' => $society->area_unit,
            'financial_year_start_month' => $society->financial_year_start_month,
            'payment_mode' => $society->payment_mode,
            'gst_enabled' => $society->gst_enabled,
        ];

        $this->preferences = [
            'offline_requires_approval' => (bool) $society->setting('payments.offline_requires_approval', true),
            'show_phone_to_residents' => (bool) $society->setting('directory.show_phone_to_residents', false),
            'auto_escalate' => (bool) $society->setting('helpdesk.auto_escalate', true),
            'escalate_after_hours' => (int) $society->setting('helpdesk.escalate_after_hours', 24),
            'require_visitor_approval' => (bool) $society->setting('visitors.require_resident_approval', true),
        ];
    }

    public function saveProfile(): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        $validated = $this->validate([
            'profile.name' => 'required|string|min:3|max:180',
            'profile.type' => 'required|string',
            'profile.registration_number' => 'nullable|string|max:60',
            'profile.gstin' => 'nullable|string|max:20',
            'profile.address_line1' => 'nullable|string|max:180',
            'profile.city' => 'nullable|string|max:80',
            'profile.state' => 'nullable|string|max:80',
            'profile.postal_code' => 'nullable|string|max:12',
            'profile.contact_email' => 'nullable|email|max:120',
            'profile.contact_phone' => 'nullable|string|max:20',
            'profile.area_unit' => 'required|in:sqft,sqm,sqyd',
            'profile.financial_year_start_month' => 'required|integer|min:1|max:12',
            'profile.payment_mode' => 'required|in:offline,online,both',
            'profile.gst_enabled' => 'boolean',
        ])['profile'];

        app(SocietyContext::class)->check()->forceFill($validated)->save();

        $this->dispatch('notify', message: 'Society details saved.', tone: 'positive');
    }

    public function savePreferences(): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        $society = app(SocietyContext::class)->check();

        $society->putSetting('payments.offline_requires_approval', (bool) $this->preferences['offline_requires_approval']);
        $society->putSetting('directory.show_phone_to_residents', (bool) $this->preferences['show_phone_to_residents']);
        $society->putSetting('helpdesk.auto_escalate', (bool) $this->preferences['auto_escalate']);
        $society->putSetting('helpdesk.escalate_after_hours', max(1, (int) $this->preferences['escalate_after_hours']));
        $society->putSetting('visitors.require_resident_approval', (bool) $this->preferences['require_visitor_approval']);
        $society->save();

        $this->dispatch('notify', message: 'Preferences saved.', tone: 'positive');
    }

    /**
     * Stores gateway keys, then verifies them against the provider before
     * activating - so a society never shows residents a checkout that fails.
     */
    public function saveGateway(GatewayManager $gateways): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        $this->validate([
            'provider' => 'required|string',
            'environment' => 'required|in:test,live',
            'keyId' => 'required|string|max:120',
            'keySecret' => 'required|string|max:200',
            'webhookSecret' => 'nullable|string|max:200',
        ]);

        if (! $gateways->supports($this->provider)) {
            $this->dispatch('notify', message: 'That provider is not supported yet.', tone: 'critical');

            return;
        }

        $society = app(SocietyContext::class)->check();

        $gateway = PaymentGateway::updateOrCreate(
            [
                'society_id' => $society->id,
                'provider' => $this->provider,
                'environment' => $this->environment,
            ],
            [
                'credentials' => ['key_id' => $this->keyId, 'key_secret' => $this->keySecret],
                'webhook_secret' => $this->webhookSecret ?: null,
                'is_default' => true,
            ],
        );

        $works = $gateways->for($gateway)->testConnection($gateway);

        $gateway->forceFill([
            'is_active' => $works,
            'verified_at' => $works ? now() : null,
        ])->save();

        if ($works && $society->payment_mode === 'offline') {
            $society->forceFill(['payment_mode' => 'both'])->save();
            $this->profile['payment_mode'] = 'both';
        }

        $this->reset(['keyId', 'keySecret', 'webhookSecret']);
        $this->dispatch('close-modal', 'gateway');
        $this->dispatch('notify',
            message: $works
                ? 'Gateway verified and activated. Residents can now pay online.'
                : 'Keys saved, but the provider rejected them. Check and try again.',
            tone: $works ? 'positive' : 'critical');
    }

    public function toggleGateway(int $gatewayId): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        $gateway = PaymentGateway::findOrFail($gatewayId);
        $gateway->forceFill(['is_active' => ! $gateway->is_active])->save();

        $this->dispatch('notify',
            message: $gateway->is_active ? 'Gateway enabled.' : 'Gateway disabled.',
            tone: 'positive');
    }

    public function render()
    {
        $society = app(SocietyContext::class)->check();

        return view('livewire.settings.society-settings', [
            'society' => $society,
            'gateways' => $society->paymentGateways()->get(),
            'acceptsOnline' => $society->acceptsOnlinePayments(),
        ])->title('Society settings');
    }
}
