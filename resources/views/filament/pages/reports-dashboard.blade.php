<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($this->getReportGroups() as $key => $group)
            <div class="space-y-3">
                {{-- Category Header --}}
                <div class="px-1">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">
                        {{ $group['label'] }}
                    </h2>
                    <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5 leading-tight">
                        {{ $group['description'] }}
                    </p>
                </div>

                {{-- List Container --}}
                <div class="bg-white dark:bg-white/[0.03] rounded-xl border border-gray-200 dark:border-white/10 overflow-hidden divide-y divide-gray-100 dark:divide-white/5 shadow-sm">
                    @forelse($group['reports'] as $report)
                        <a href="{{ $report['url'] }}" 
                           target="_blank"
                           class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[0.02] transition-colors group no-underline"
                        >
                            {{-- Color Dot --}}
                            <span @class([
                                'w-2 h-2 rounded-full shrink-0',
                                'bg-emerald-500' => $report['color'] === 'success',
                                'bg-blue-500' => $report['color'] === 'primary',
                                'bg-red-500' => $report['color'] === 'danger',
                                'bg-warning-500' => $report['color'] === 'warning',
                                'bg-gray-400' => $report['color'] === 'gray',
                                'bg-indigo-500' => $report['color'] === 'indigo',
                            ])></span>

                            {{-- Text --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-[13px] font-bold text-gray-800 dark:text-gray-100 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors truncate leading-none">
                                        {{ $report['name'] }}
                                    </span>
                                    <span @class([
                                        'shrink-0 text-[8px] font-black uppercase tracking-tighter px-1 py-0.5 rounded leading-none',
                                        'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-400' => $report['type'] === 'API',
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $report['type'] === 'Excel',
                                        'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' => $report['type'] === 'PDF' || $report['type'] === 'PDF/Print',
                                    ])>
                                        {{ $report['type'] }}
                                    </span>
                                </div>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1 truncate leading-none">
                                    {{ $report['description'] }}
                                </p>
                            </div>

                            {{-- Little Arrow --}}
                            <span class="text-gray-200 dark:text-gray-700 group-hover:text-primary-400 transition-colors shrink-0">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                                </svg>
                            </span>
                        </a>
                    @empty
                        <div class="px-4 py-8 text-center text-[11px] text-gray-400 border-none">
                            No reports.
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>