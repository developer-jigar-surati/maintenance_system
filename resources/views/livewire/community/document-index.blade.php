<div>
    <x-ui.page-header title="Documents" description="Bye-laws, audited accounts, minutes and circulars.">
        <x-slot:actions>
            @can(\App\Enums\Permission::DOCUMENT_MANAGE)
                <x-ui.button x-on:click="$dispatch('open-modal', 'upload-document')" icon="plus">Upload</x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.table
        :headers="['Document', 'Category', 'Size', 'Uploaded', 'Visibility', '']"
        :is-empty="$documents->isEmpty()"
        empty="No documents available to you"
        empty-icon="folder"
        caption="Documents with category, size and visibility"
    >
        <x-slot:toolbar>
            <div class="w-full sm:max-w-xs">
                <x-ui.input wire:model.live.debounce.300ms="search" type="search" icon="search"
                    placeholder="Document title" aria-label="Search documents" />
            </div>
            <x-ui.select wire:model.live="category" aria-label="Filter by category" class="w-auto min-w-36">
                <option value="">All categories</option>
                @foreach (['bye_laws', 'registration', 'audit_report', 'financial_statement', 'agm_minutes', 'circular', 'legal', 'insurance', 'floor_plan', 'noc', 'agreement', 'other'] as $c)
                    <option value="{{ $c }}">{{ ucwords(str_replace('_', ' ', $c)) }}</option>
                @endforeach
            </x-ui.select>
            @if ($this->hasActiveFilters())
                <x-ui.button wire:click="clearFilters" variant="ghost" size="sm" icon="close">Clear</x-ui.button>
            @endif
        </x-slot:toolbar>

        @foreach ($documents as $document)
            <x-ui.tr>
                <x-ui.td label="Document" primary>
                    {{ $document->title }}
                    <span class="block truncate text-xs text-muted">{{ $document->file_name }}</span>
                </x-ui.td>
                <x-ui.td label="Category">{{ $document->categoryLabel() }}</x-ui.td>
                <x-ui.td label="Size"><span class="numeric">{{ $document->humanSize() }}</span></x-ui.td>
                <x-ui.td label="Uploaded">
                    {{ $document->created_at->format('j M Y') }}
                    <span class="block text-xs text-muted">{{ $document->uploadedBy?->name }}</span>
                </x-ui.td>
                <x-ui.td label="Visibility">
                    <x-ui.badge tone="neutral">{{ ucwords(str_replace('_', ' ', $document->visibility)) }}</x-ui.badge>
                </x-ui.td>
                <x-ui.td align="right">
                    <button type="button" wire:click="download({{ $document->id }})"
                        class="inline-flex items-center gap-1 text-xs font-semibold accent-text hover:underline">
                        <x-ui.icon name="download" class="size-3.5" /> Download
                    </button>
                </x-ui.td>
            </x-ui.tr>
        @endforeach

        <x-slot:footer>{{ $documents->links() }}</x-slot:footer>
    </x-ui.table>

    @can(\App\Enums\Permission::DOCUMENT_MANAGE)
        <x-ui.modal name="upload-document" title="Upload a document">
            <form wire:submit="save" class="space-y-4" id="upload-document-form">
                <x-ui.input wire:model="title" name="title" label="Title" required />

                <x-ui.select wire:model="newCategory" name="newCategory" label="Category">
                    @foreach (['bye_laws', 'registration', 'audit_report', 'financial_statement', 'agm_minutes', 'circular', 'legal', 'insurance', 'floor_plan', 'noc', 'agreement', 'other'] as $c)
                        <option value="{{ $c }}">{{ ucwords(str_replace('_', ' ', $c)) }}</option>
                    @endforeach
                </x-ui.select>

                <x-ui.select wire:model="visibility" name="visibility" label="Who can see it">
                    <option value="all">Everyone</option>
                    <option value="owners">Owners only</option>
                    <option value="committee">Committee only</option>
                    <option value="admin_only">Administrators only</option>
                </x-ui.select>

                <div>
                    <label for="document-upload" class="mb-1.5 block text-sm font-medium">File</label>
                    <input
                        type="file"
                        id="document-upload"
                        wire:model="upload"
                        class="w-full rounded-xl border border-subtle surface-raised px-3.5 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:px-3 file:py-1.5 file:text-sm file:font-semibold"
                        required
                    >
                    <p class="mt-1.5 text-xs text-muted">PDF, Office, image or text. Up to 20&nbsp;MB.</p>
                    @error('upload')
                        <p class="mt-1.5 text-xs font-medium text-[var(--color-critical)]">{{ $message }}</p>
                    @enderror
                    <p class="mt-1.5 text-xs text-muted" wire:loading wire:target="upload">Uploading&hellip;</p>
                </div>
            </form>

            <x-slot:footer>
                <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'upload-document')">Cancel</x-ui.button>
                <x-ui.button type="submit" form="upload-document-form">Upload</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endcan
</div>
