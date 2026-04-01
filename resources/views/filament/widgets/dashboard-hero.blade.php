<x-filament-widgets::widget>
    <x-filament::section>
        <div style="padding: 1.25rem; border-radius: 1rem; border: 1px solid rgba(245, 158, 11, 0.25); background: linear-gradient(100deg, rgba(245, 158, 11, 0.15), rgba(59, 130, 246, 0.12));">
            <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem;">
                <div style="display: grid; gap: 0.45rem; max-width: 820px;">
                    <p style="margin: 0; font-size: 0.85rem; color: rgba(255, 255, 255, 0.78);">
                        {{ $todayLabel }}
                    </p>
                    <h2 style="margin: 0; font-size: 1.55rem; line-height: 1.2; font-weight: 700; color: #fff;">
                        Operations Command Center
                    </h2>
                    <p style="margin: 0; font-size: 0.95rem; color: rgba(255, 255, 255, 0.9);">
                        Review loan pipeline, monitor collections, and jump to critical workflows in one click.
                    </p>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    <x-filament::button tag="a" size="sm" color="warning" :href="\App\Filament\Resources\Loans\LoanResource::getUrl('create')">
                        New Loan
                    </x-filament::button>
                    <x-filament::button tag="a" size="sm" color="gray" outlined :href="\App\Filament\Resources\Borrowers\BorrowerResource::getUrl('create')">
                        New Borrower
                    </x-filament::button>
                    <x-filament::button tag="a" size="sm" color="gray" outlined :href="\App\Filament\Pages\ReportsDashboard::getUrl()">
                        Open Reports
                    </x-filament::button>
                    <x-filament::button tag="a" size="sm" color="gray" outlined :href="\App\Filament\Pages\ManageSettings::getUrl()">
                        Settings
                    </x-filament::button>
                </div>
            </div>

            <div style="margin-top: 1rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.6rem;">
                <div style="padding: 0.75rem; border-radius: 0.75rem; border: 1px solid rgba(255, 255, 255, 0.14); background: rgba(8, 15, 30, 0.35);">
                    <p style="margin: 0; font-size: 0.75rem; color: rgba(255, 255, 255, 0.75);">Due Today</p>
                    <p style="margin: 0.35rem 0 0; font-size: 1.2rem; font-weight: 700; color: #fff;">{{ number_format($dueToday) }}</p>
                </div>
                <div style="padding: 0.75rem; border-radius: 0.75rem; border: 1px solid rgba(255, 255, 255, 0.14); background: rgba(8, 15, 30, 0.35);">
                    <p style="margin: 0; font-size: 0.75rem; color: rgba(255, 255, 255, 0.75);">Overdue Installments</p>
                    <p style="margin: 0.35rem 0 0; font-size: 1.2rem; font-weight: 700; color: #fff;">{{ number_format($overdueInstallments) }}</p>
                </div>
                <div style="padding: 0.75rem; border-radius: 0.75rem; border: 1px solid rgba(255, 255, 255, 0.14); background: rgba(8, 15, 30, 0.35);">
                    <p style="margin: 0; font-size: 0.75rem; color: rgba(255, 255, 255, 0.75);">PAR 30</p>
                    <p style="margin: 0.35rem 0 0; font-size: 1.2rem; font-weight: 700; color: #fff;">{{ number_format($par30, 2) }}%</p>
                </div>
                <div style="padding: 0.75rem; border-radius: 0.75rem; border: 1px solid rgba(255, 255, 255, 0.14); background: rgba(8, 15, 30, 0.35);">
                    <p style="margin: 0; font-size: 0.75rem; color: rgba(255, 255, 255, 0.75);">Today's Collections</p>
                    <p style="margin: 0.35rem 0 0; font-size: 1.2rem; font-weight: 700; color: #fff;">${{ number_format($todayCollections, 2) }}</p>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
