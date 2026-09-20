<?php

namespace App\Livewire\Community;

use App\Enums\Permission;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Document;
use App\Support\SocietyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class DocumentIndex extends Component
{
    use WithDataTable, WithFileUploads;

    #[Url(except: '')]
    public string $category = '';

    public $upload;

    public string $title = '';

    public string $newCategory = 'other';

    public string $visibility = 'all';

    public function save(): void
    {
        Gate::authorize(Permission::DOCUMENT_MANAGE);

        $this->validate([
            'upload' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,txt,csv',
            'title' => 'required|string|min:2|max:180',
            'newCategory' => 'required|string',
            'visibility' => 'required|in:all,owners,committee,admin_only',
        ]);

        $society = app(SocietyContext::class)->check();

        // Stored per society so one society's vault can never serve another's.
        $path = $this->upload->store("societies/{$society->id}/documents", 'local');

        Document::create([
            'society_id' => $society->id,
            'title' => $this->title,
            'category' => $this->newCategory,
            'visibility' => $this->visibility,
            'file_path' => $path,
            'file_name' => $this->upload->getClientOriginalName(),
            'mime_type' => $this->upload->getMimeType(),
            'file_size' => $this->upload->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        $this->reset(['upload', 'title']);
        $this->dispatch('close-modal', 'upload-document');
        $this->dispatch('notify', message: 'Document uploaded.', tone: 'positive');
    }

    public function download(int $documentId)
    {
        $document = Document::findOrFail($documentId);

        abort_unless($document->isVisibleTo(auth()->user()), 403);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    protected function sortableColumns(): array
    {
        return ['title', 'created_at', 'category'];
    }

    protected function filterProperties(): array
    {
        return ['category'];
    }

    public function render()
    {
        $user = auth()->user();

        $query = Document::query()
            ->files()
            ->with('uploadedBy')
            ->when($this->search !== '', fn (Builder $q) => $q->where('title', 'like', "%{$this->search}%"))
            ->when($this->category !== '', fn (Builder $q) => $q->where('category', $this->category));

        $documents = $this->applySort($query)->paginate($this->perPage);

        $documents->setCollection(
            $documents->getCollection()->filter(fn (Document $d) => $d->isVisibleTo($user))->values()
        );

        return view('livewire.community.document-index', [
            'documents' => $documents,
            'canManage' => $user->can(Permission::DOCUMENT_MANAGE),
        ])->title('Documents');
    }
}
