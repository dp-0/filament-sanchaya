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
	@sanchaya:open-uploader.window="
		const modalId = $event.detail?.id ?? 'sanchaya-upload';
		if (modalId === 'sanchaya-upload') {
			$dispatch('open-modal', { id: modalId });
		}
	"
	@close-modal.window="
		if ($event.detail?.id === 'sanchaya-upload') {
			$wire.discardPendingUploads()
		}

		if ($event.detail?.id === 'sanchaya-detail') {
			$wire.closeDetailPanel()
		}
	"
	@sanchaya:notify.window="notify($event)"
	class="sanchaya-manager flex flex-col h-full bg-white dark:bg-gray-900"
>

	@include('filament-sanchaya::partials.toolbar', ['isPicker' => false])

	@include('filament-sanchaya::partials.breadcrumb')

	@if (count($checkedIds) > 0)
		<div class="flex items-center gap-3 px-4 py-2
					bg-primary-50 dark:bg-primary-950/40
					border-b border-primary-200 dark:border-primary-800
					text-sm">

			<span class="font-medium text-primary-700 dark:text-primary-300">
				{{ count($checkedIds) }} selected
			</span>

			@if ($this->actionEnabled('download'))
				<x-filament::button
					type="button"
					size="sm"
					color="gray"
					:icon="$this->actionConfig('download')['icon']"
					wire:click="bulkDownload"
				>
					{{ $this->actionConfig('download')['label'] }}
				</x-filament::button>
			@endif

			@if ($this->actionEnabled('delete'))
				<x-filament::button
					type="button"
					size="sm"
					color="danger"
					:icon="$this->actionConfig('delete')['icon']"
					wire:click="confirmDelete"
				>
					{{ $this->actionConfig('delete')['label'] }}
				</x-filament::button>
			@endif

			<button
				type="button"
				wire:click="clearSelection"
				title="Clear selection"
				class="ml-auto p-1 rounded text-gray-400 hover:text-gray-600
					   dark:hover:text-gray-200 transition-colors"
			>
				<x-heroicon-m-x-mark class="w-4 h-4" />
			</button>
		</div>
	@endif

	<div class="flex flex-1 overflow-hidden">
		@include('filament-sanchaya::partials.sidebar')

		<div class="flex-1 overflow-y-auto p-4 flex flex-col">
			@if ($this->items->isEmpty())
				<div class="flex flex-col items-center justify-center flex-1
							text-gray-400 dark:text-gray-500">
					<x-heroicon-o-folder-open class="w-16 h-16 mb-3 opacity-40" />
					<p class="text-sm font-medium">
						@if ($search !== '' || $mimeFilter !== '')
							No files match your filters.
						@else
							This folder is empty.
						@endif
					</p>
					@if ($search !== '' || $mimeFilter !== '')
						<x-filament::button
							type="button"
							size="sm"
							color="gray"
							wire:click="clearFilters"
							class="mt-3"
						>
							Clear filters
						</x-filament::button>
					@endif
				</div>
			@elseif ($viewMode === 'grid')
				<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4
							lg:grid-cols-5 xl:grid-cols-6 gap-3">
					@foreach ($this->items as $item)
									@include('filament-sanchaya::partials.grid-item', ['item' => $item, 'isPicker' => false])
					@endforeach
				</div>
			@else
				<div class="w-full overflow-x-auto">
					<table class="w-full text-sm text-left">
						<thead class="text-xs uppercase
									  text-gray-500 dark:text-gray-400
									  border-b border-gray-200 dark:border-gray-700
									  sticky top-0 bg-white dark:bg-gray-900">
						<tr>
							<th class="px-3 py-2 w-8">
								<input
									type="checkbox"
									wire:click="toggleSelectAll"
									:checked="$wire.selectAll"
									class="rounded border-gray-300 dark:border-gray-600
											   bg-white dark:bg-gray-800"
								/>
							</th>
							<th class="px-3 py-2">
								<button
									type="button"
									wire:click="setSortBy('name')"
									class="flex items-center gap-1
											   hover:text-gray-900 dark:hover:text-gray-100
											   transition-colors"
								>
									Name
									@if ($sortBy === 'name')
										<x-heroicon-m-chevron-up-down class="w-3 h-3" />
									@endif
								</button>
							</th>
							<th class="px-3 py-2 hidden md:table-cell">
								<button
									type="button"
									wire:click="setSortBy('size')"
									class="flex items-center gap-1
											   hover:text-gray-900 dark:hover:text-gray-100
											   transition-colors"
								>
									Size
									@if ($sortBy === 'size')
										<x-heroicon-m-chevron-up-down class="w-3 h-3" />
									@endif
								</button>
							</th>
							<th class="px-3 py-2 hidden lg:table-cell">Type</th>
							<th class="px-3 py-2 hidden lg:table-cell">
								<button
									type="button"
									wire:click="setSortBy('date')"
									class="flex items-center gap-1
											   hover:text-gray-900 dark:hover:text-gray-100
											   transition-colors"
								>
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
							@include('filament-sanchaya::partials.list-item', ['item' => $item, 'isPicker' => false])
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

	<x-filament::modal id="sanchaya-detail" width="5xl">
		<x-slot name="heading">File Details</x-slot>

		@if ($showDetailPanel && $this->previewFile)
			@include('filament-sanchaya::partials.detail-panel', ['file' => $this->previewFile])
		@endif
	</x-filament::modal>

	<x-filament::modal id="sanchaya-rename" width="sm">
		<x-slot name="heading">Rename</x-slot>

		<div class="space-y-3">
			<div>
				<input
					type="text"
					wire:model="renameValue"
					wire:keydown.enter="rename"
					autofocus
					class="w-full px-3 py-2 text-sm rounded-lg
						   border border-gray-300 dark:border-gray-600
						   bg-white dark:bg-gray-800
						   text-gray-700 dark:text-gray-200
						   focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
				/>
				@error('renameValue')
				<p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
				@enderror
			</div>
		</div>

		<x-slot name="footerActions">
			<x-filament::button type="button" wire:click="rename">Rename</x-filament::button>
			<x-filament::button
				type="button"
				color="gray"
				x-on:click="$dispatch('close-modal', { id: 'sanchaya-rename' })"
			>Cancel</x-filament::button>
		</x-slot>
	</x-filament::modal>

	<x-filament::modal id="sanchaya-move" width="sm">
		<x-slot name="heading">Move To</x-slot>

		<div class="space-y-1">
			<button
				type="button"
				wire:click="$set('moveDestinationId', null)"
				@class([
					'w-full flex items-center gap-2 px-3 py-2 rounded-lg text-sm transition-colors',
					'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300' => $moveDestinationId === null,
					'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800' => $moveDestinationId !== null,
				])
			>
				<x-heroicon-o-home class="w-4 h-4 shrink-0" />
				Root
			</button>

			@foreach ($this->folderTree as $folder)
				@include('filament-sanchaya::partials.folder-tree-item', ['folder' => $folder, 'depth' => 0])
			@endforeach

			@error('moveDestinationId')
			<p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
			@enderror
		</div>

		<x-slot name="footerActions">
			<x-filament::button type="button" wire:click="move">Move Here</x-filament::button>
			<x-filament::button
				type="button"
				color="gray"
				x-on:click="$dispatch('close-modal', { id: 'sanchaya-move' })"
			>Cancel</x-filament::button>
		</x-slot>
	</x-filament::modal>

	<x-filament::modal id="sanchaya-delete" :close-by-clicking-away="false" width="sm">
		<x-slot name="heading">Confirm Delete</x-slot>
		<x-slot name="description">
			@if ($deletingId !== null)
				Are you sure you want to delete this item? This cannot be undone.
			@else
				Are you sure you want to delete {{ count($checkedIds) }} selected item(s)?
			@endif
		</x-slot>

		<x-slot name="footerActions">
			<x-filament::button type="button" color="danger" wire:click="delete">Delete</x-filament::button>
			<x-filament::button
				type="button"
				color="gray"
				x-on:click="$dispatch('close-modal', { id: 'sanchaya-delete' })"
			>Cancel</x-filament::button>
		</x-slot>
	</x-filament::modal>

</div>

