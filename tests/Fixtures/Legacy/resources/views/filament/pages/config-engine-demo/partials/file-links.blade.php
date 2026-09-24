@if ($withContainer)
    <div class="space-y-1">
@endif
    @foreach ($files as $file)
        <a
            href="{{ $file->file_path }}"
            target="_blank"
            rel="noopener noreferrer"
            class="mb-2 flex items-center justify-between rounded border border-gray-200 p-2 transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
        >
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ $file->title }}</span>
            <span class="text-xs text-primary-600 dark:text-primary-400">Open</span>
        </a>
    @endforeach
@if ($withContainer)
    </div>
@endif
