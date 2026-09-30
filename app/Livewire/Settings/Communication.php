<?php

namespace App\Livewire\Settings;

use App\Enums\Permission;
use App\Models\MessageDispatch;
use App\Models\MessageTemplate;
use App\Models\ReminderRule;
use App\Services\Messaging\TemplateRenderer;
use App\Support\MessageCatalogue;
use App\Support\SocietyContext;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Where a committee decides what the system says and when it says it.
 *
 * Two questions live here because they are really one: "how often do we chase
 * people" and "in what words". Splitting them across screens made it possible
 * to change a schedule without ever reading the message it sends.
 */
#[Layout('components.layouts.app')]
class Communication extends Component
{
    #[Url(except: 'schedule')]
    public string $tab = 'schedule';

    // --- reminder schedule -------------------------------------------------

    public string $event = 'invoice_due';

    /** Offsets in days, keyed by rule id, so the whole ladder saves at once. */
    public array $rules = [];

    public int $newOffset = 14;

    public string $newLabel = '';

    // --- template editor ---------------------------------------------------

    public string $editingKey = '';

    public string $editingChannel = 'email';

    public string $subject = '';

    public string $body = '';

    public function mount(): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        $this->loadRules();
    }

    private function loadRules(): void
    {
        $this->rules = ReminderRule::query()
            ->forEvent($this->event)
            ->orderBy('offset_days')
            ->get()
            ->mapWithKeys(fn (ReminderRule $rule) => [$rule->id => [
                'offset_days' => $rule->offset_days,
                'label' => (string) $rule->label,
                'is_active' => $rule->is_active,
                'channels' => $rule->channels ?: ['email'],
            ]])
            ->all();
    }

    public function updatedEvent(): void
    {
        $this->loadRules();
    }

    public function addStep(): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        $this->validate([
            'newOffset' => 'required|integer|min:-90|max:365',
            'newLabel' => 'nullable|string|max:120',
        ]);

        $society = app(SocietyContext::class)->check();

        $exists = ReminderRule::query()
            ->forEvent($this->event)
            ->where('offset_days', $this->newOffset)
            ->exists();

        if ($exists) {
            $this->addError('newOffset', 'There is already a reminder on that day.');

            return;
        }

        ReminderRule::create([
            'society_id' => $society->id,
            'event' => $this->event,
            'offset_days' => $this->newOffset,
            'label' => $this->newLabel ?: null,
            'template_key' => $this->defaultTemplateFor($this->event),
            'channels' => ['email'],
            'is_active' => true,
        ]);

        $this->reset(['newLabel']);
        $this->loadRules();
        $this->dispatch('notify', message: 'Reminder step added.', tone: 'positive');
    }

    public function toggleStep(int $id): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        $rule = ReminderRule::findOrFail($id);
        $rule->forceFill(['is_active' => ! $rule->is_active])->save();

        $this->loadRules();
    }

    public function removeStep(int $id): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        ReminderRule::findOrFail($id)->delete();

        $this->loadRules();
        $this->dispatch('notify', message: 'Reminder step removed.', tone: 'positive');
    }

    public function saveSchedule(): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        foreach ($this->rules as $id => $values) {
            $rule = ReminderRule::find($id);

            if ($rule === null) {
                continue;
            }

            $channels = array_values(array_filter((array) ($values['channels'] ?? [])));

            $rule->forceFill([
                'label' => $values['label'] ?: null,
                'is_active' => (bool) ($values['is_active'] ?? false),
                'channels' => $channels === [] ? ['email'] : $channels,
            ])->save();
        }

        $this->loadRules();
        $this->dispatch('notify', message: 'Reminder schedule saved.', tone: 'positive');
    }

    // --- templates ----------------------------------------------------------

    public function edit(string $key, string $channel = 'email'): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        abort_unless(in_array($key, MessageCatalogue::keys(), true), 404);

        $resolved = app(TemplateRenderer::class)
            ->resolve(app(SocietyContext::class)->check(), $key, $channel);

        $this->editingKey = $key;
        $this->editingChannel = $channel;
        $this->subject = $resolved['subject'];
        $this->body = $resolved['body'];

        $this->dispatch('open-modal', 'edit-template');
    }

    public function saveTemplate(): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        $this->validate([
            'subject' => 'nullable|string|max:200',
            'body' => 'required|string|min:10|max:8000',
        ]);

        $society = app(SocietyContext::class)->check();

        MessageTemplate::updateOrCreate(
            [
                'society_id' => $society->id,
                'key' => $this->editingKey,
                'channel' => $this->editingChannel,
            ],
            [
                'name' => MessageCatalogue::get($this->editingKey)['name'] ?? $this->editingKey,
                'subject' => $this->subject ?: null,
                'body' => $this->body,
                'is_active' => true,
                'updated_by' => auth()->id(),
            ],
        );

        $this->dispatch('close-modal', 'edit-template');
        $this->dispatch('notify', message: 'Wording saved.', tone: 'positive');
        $this->editingKey = '';
    }

    /** Deleting the override is what "use the standard wording" means. */
    public function resetTemplate(string $key, string $channel = 'email'): void
    {
        Gate::authorize(Permission::SOCIETY_SETTINGS);

        MessageTemplate::query()->forKey($key, $channel)->delete();

        $this->dispatch('notify', message: 'Reverted to the standard wording.', tone: 'positive');
    }

    private function defaultTemplateFor(string $event): string
    {
        return match ($event) {
            'invoice_due' => MessageCatalogue::PAYMENT_REMINDER,
            'meeting' => MessageCatalogue::MEETING_NOTICE,
            'complaint_breach' => MessageCatalogue::COMPLAINT_UPDATE,
            default => MessageCatalogue::PAYMENT_REMINDER,
        };
    }

    public function render()
    {
        $society = app(SocietyContext::class)->check();
        $renderer = app(TemplateRenderer::class);

        $overrides = MessageTemplate::query()->get()->keyBy(fn ($t) => $t->key.':'.$t->channel);

        $templates = collect(MessageCatalogue::all())
            ->map(fn (array $entry, string $key) => $entry + [
                'key' => $key,
                'customised' => $overrides->has($key.':email'),
                'updated_at' => $overrides->get($key.':email')?->updated_at,
            ])
            ->values();

        $preview = null;

        if ($this->editingKey !== '') {
            $data = $renderer->sampleData($society, $this->editingKey);

            $preview = [
                'subject' => $renderer->substitute($this->subject, $data),
                'body' => $renderer->substitute($this->body, $data),
                'unknown' => $renderer->unknownTokens($this->body.' '.$this->subject, $this->editingKey),
                'placeholders' => MessageCatalogue::placeholders($this->editingKey),
            ];
        }

        return view('livewire.settings.communication', [
            'society' => $society,
            'templates' => $templates,
            'preview' => $preview,
            'events' => ReminderRule::EVENTS,
            'recent' => MessageDispatch::query()
                ->with('user')
                ->latest()
                ->limit(15)
                ->get(),
            'sentToday' => MessageDispatch::query()->sent()->whereDate('created_at', today())->count(),
        ])->title('Reminders & messages');
    }
}
