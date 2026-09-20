<?php

namespace App\Livewire\Community;

use App\Enums\Permission;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Notice;
use App\Support\SocietyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
class NoticeIndex extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $category = '';

    public string $title = '';

    public string $body = '';

    public string $newCategory = 'general';

    public string $priority = 'normal';

    public string $audience = 'all';

    public bool $pinned = false;

    public function publish(): void
    {
        Gate::authorize(Permission::NOTICE_MANAGE);

        $validated = $this->validate([
            'title' => 'required|string|min:4|max:180',
            'body' => 'required|string|min:10',
            'newCategory' => 'required|string',
            'priority' => 'required|in:normal,important,critical',
            'audience' => 'required|in:all,owners,tenants,committee,staff',
        ]);

        $notice = Notice::create([
            'society_id' => app(SocietyContext::class)->check()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'category' => $validated['newCategory'],
            'priority' => $validated['priority'],
            'audience' => $validated['audience'],
            'is_pinned' => $this->pinned,
            'status' => 'published',
            'published_at' => now(),
            'created_by' => auth()->id(),
        ]);

        $this->reset(['title', 'body', 'pinned']);
        $this->dispatch('close-modal', 'new-notice');
        $this->dispatch('notify', message: 'Notice published.', tone: 'positive');
    }

    public function markRead(int $noticeId): void
    {
        Notice::findOrFail($noticeId)->markReadBy(auth()->user());
    }

    protected function defaultSort(): array
    {
        return ['published_at', 'desc'];
    }

    protected function sortableColumns(): array
    {
        return ['published_at', 'title'];
    }

    protected function filterProperties(): array
    {
        return ['category'];
    }

    public function render()
    {
        $user = auth()->user();
        $canManage = $user->can(Permission::NOTICE_MANAGE);

        $query = Notice::query()
            ->with('createdBy')
            ->withCount('reads')
            // Drafts are only useful to whoever can publish them.
            ->when(! $canManage, fn (Builder $q) => $q->published())
            ->when($this->search !== '', fn (Builder $q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->category !== '', fn (Builder $q) => $q->where('category', $this->category))
            ->orderByDesc('is_pinned');

        $notices = $this->applySort($query)->paginate($this->perPage);

        // Audience filtering is a per-notice rule, so it is applied after the
        // query rather than being expressible in SQL.
        $notices->setCollection(
            $notices->getCollection()->filter(fn (Notice $n) => $canManage || $n->isVisibleTo($user))->values()
        );

        return view('livewire.community.notice-index', [
            'notices' => $notices,
            'canManage' => $canManage,
        ])->title('Notices');
    }
}
