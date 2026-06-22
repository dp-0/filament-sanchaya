<div
	x-data="{
		notify(event) {
			if (typeof $notification !== 'undefined') {
				$notification({
					title: event.detail.message,
					status: event.detail.type,
				});
			}
		}
	}"
	@close-modal.window="
		if ($event.detail?.id === 'sanchaya-upload') {
			$wire.discardPendingUploads()
		}
	"
	@sanchaya:notify.window="notify($event)"
	class="sanchaya-manager flex flex-col h-full bg-white dark:bg-gray-900"
>
	@include('filament-sanchaya::partials.toolbar', ['isPicker' => true])

	@include('filament-sanchaya::partials.breadcrumb')

	<div class="flex flex-1 overflow-hidden">
		<div class="flex-1 overflow-y-auto p-4 flex flex-col">
			@if ($this->items->isEmpty())
				<div class="flex flex-col items-center justify-center flex-1 text-gray-400 dark:text-gray-500">
					<x-heroicon-o-folder-open class="w-16 h-16 mb-3 opacity-40" />
					<p class="text-sm font-medium">
						@if ($search !== '' || $mimeFilter !== '')
							No files match your filters.
						@else
							This folder is empty.
						@endif
					</p>
					@if ($search !== '' || $mimeFilter !== '')
						<x-filament::button type="button" size="sm" color="gray" wire:click="clearFilters" class="mt-3">
							Clear filters
						</x-filament::button>
					@endif
				</div>
			@elseif ($viewMode === 'grid')
				<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
					@foreach ($this->items as $item)
						@include('filament-sanchaya::partials.grid-item', ['item' => $item, 'isPicker' => true])
					@endforeach
				</div>
			@else
				<div class="w-full overflow-x-auto">
					<table class="w-full text-sm text-left">
						<thead class="text-xs uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-900">
						<tr>
							<th class="px-3 py-2 w-8">
								@if ($this->allowsBulkSelection())
									<input
										type="checkbox"
										wire:click="toggleSelectAll"
										:checked="$wire.selectAll"
										class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800"
									/>
								@endif
							</th>
							<th class="px-3 py-2">
								<button type="button" wire:click="setSortBy('name')" class="flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100 transition-colors">
									Name
									@if ($sortBy === 'name')
										<x-heroicon-m-chevron-up-down class="w-3 h-3" />
									@endif
								</button>
							</th>
							<th class="px-3 py-2 hidden md:table-cell">
								<button type="button" wire:click="setSortBy('size')" class="flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100 transition-colors">
									Size
									@if ($sortBy === 'size')
										<x-heroicon-m-chevron-up-down class="w-3 h-3" />
									@endif
								</button>
							</th>
							<th class="px-3 py-2 hidden lg:table-cell">Type</th>
							<th class="px-3 py-2 hidden lg:table-cell">
								<button type="button" wire:click="setSortBy('date')" class="flex items-center gap-1 hover:text-gray-900 dark:hover:text-gray-100 transition-colors">
									Date
									@if ($sortBy === 'date')
										<x-heroicon-m-chevron-up-down class="w-3 h-3" />
									@endif
								</button>
							</th>
							<th class="px-3 py-2 text-right">Actions</th>
						</tr>
						</thead>
						<tbody class="divide-y divide-gray-100 dark:divide-gray-800">
						@foreach ($this->items as $item)
							@include('filament-sanchaya::partials.list-item', ['item' => $item, 'isPicker' => true])
						@endforeach
						</tbody>
					</table>
				</div>
			@endif

			@if ($this->items->hasPages())
				<div class="mt-4">
					{{ $this->items->links() }}
				</div>
			@endif
		</div>
	</div>

	@if ($multiple && count($selectedIds) > 0)
		<div class="flex items-center justify-between px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
			<span class="text-sm text-gray-600 dark:text-gray-400">
				{{ count($selectedIds) }} file(s) selected
			</span>
			<x-filament::button type="button" wire:click="confirmSelection" size="sm">
				Confirm Selection
			</x-filament::button>
		</div>
	@endif

	<x-filament::modal id="sanchaya-upload" :close-by-clicking-away="false" width="2xl">
		<x-slot name="heading">Upload Files</x-slot>
		<x-slot name="description">
			Select files to upload to @if($currentFolderId)
				{{ $this->currentFolder?->display_name }}
			@else
				Root
			@endif
		</x-slot>
		@include('filament-sanchaya::partials.uploader-filament')
	</x-filament::modal>

	<x-filament::modal id="sanchaya-create-folder" width="sm">
		<x-slot name="heading">New Folder</x-slot>

		<div class="space-y-3">
			<div>
				<input
					type="text"
					wire:model="newFolderName"
					wire:keydown.enter="createFolder"
					placeholder="Folder name"
					autofocus
					class="w-full px-3 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 placeholder-gray-400 dark:placeholder-gray-500 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
				/>
				@error('newFolderName')
				<p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
				@enderror
			</div>
		</div>

		<x-slot name="footerActions">
			<x-filament::button type="button" wire:click="createFolder">Create</x-filament::button>
			<x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: 'sanchaya-create-folder' })">Cancel</x-filament::button>
		</x-slot>
	</x-filament::modal>
</div>

