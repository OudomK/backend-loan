<div class="space-y-3">
    @forelse ($changes as $change)
        <div class="flex flex-col gap-2 rounded-xl border border-white/10 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-white">
                    {{ $change['field'] }}
                </p>
            </div>

            <div class="min-w-0 text-sm sm:text-right">
                <span class="font-mono text-gray-400">
                    {{ $change['before'] ?? '-' }}
                </span>
                <span class="mx-2 font-mono text-gray-500">
                    -&gt;
                </span>
                <span class="font-mono text-success-400">
                    {{ $change['after'] ?? '-' }}
                </span>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-white/10 px-4 py-3 text-sm text-gray-400">
            No field-level changes were captured for this activity.
        </div>
    @endforelse
</div>
