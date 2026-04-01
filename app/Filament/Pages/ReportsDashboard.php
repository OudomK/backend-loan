<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ReportsDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationLabel = 'Reports';
    protected static ?string $title = 'Business Reports';
    protected static ?int $navigationSort = 14;

    protected string $view = 'filament.pages.reports-dashboard';

    public function getReportGroups(): array
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $baseUrl = $appUrl . '/api/reports';
        $exportUrl = $appUrl . '/api/export';

        return [
            'operational' => [
                'label' => 'Operational',
                'description' => 'Daily loan management and portfolio tracking.',
                'icon' => 'heroicon-o-briefcase',
                'color' => 'primary',
                'reports' => [
                    [
                        'name' => 'Active Loans',
                        'description' => 'Current active loan accounts.',
                        'url' => $baseUrl . '/active-loan',
                        'type' => 'API',
                        'icon' => 'heroicon-o-document-text',
                        'color' => 'success',
                    ],
                    [
                        'name' => 'Disbursements',
                        'description' => 'Funds released history.',
                        'url' => $baseUrl . '/disbursement',
                        'type' => 'API',
                        'icon' => 'heroicon-o-arrow-trending-up',
                        'color' => 'primary',
                    ],
                    [
                        'name' => 'Arrear (All)',
                        'description' => 'Full overdue payments list.',
                        'url' => $baseUrl . '/arrear-all',
                        'type' => 'API',
                        'icon' => 'heroicon-o-exclamation-triangle',
                        'color' => 'danger',
                    ],
                    [
                        'name' => 'Arrear (< 30 Days)',
                        'description' => 'Early-stage late payments.',
                        'url' => $baseUrl . '/arrear-under-30',
                        'type' => 'API',
                        'icon' => 'heroicon-o-clock',
                        'color' => 'warning',
                    ],
                    [
                        'name' => 'Repayment Collection',
                        'description' => 'All collected repayments.',
                        'url' => $baseUrl . '/repayment',
                        'type' => 'API',
                        'icon' => 'heroicon-o-arrow-down-left',
                        'color' => 'success',
                    ],
                    [
                        'name' => 'Inactive Loans',
                        'description' => 'Settled or closed accounts.',
                        'url' => $baseUrl . '/inactive-loan',
                        'type' => 'API',
                        'icon' => 'heroicon-o-archive-box',
                        'color' => 'gray',
                    ],
                ],
            ],
            'financial' => [
                'label' => 'Financial',
                'description' => 'Revenue, dividends, and statements.',
                'icon' => 'heroicon-o-banknotes',
                'color' => 'success',
                'reports' => [
                    [
                        'name' => 'Interest Income',
                        'description' => 'Interest revenue generated.',
                        'url' => $baseUrl . '/interest-income',
                        'type' => 'API',
                        'icon' => 'heroicon-o-currency-dollar',
                        'color' => 'primary',
                    ],
                    [
                        'name' => 'Income Statement',
                        'description' => 'Profit & Loss (P&L).',
                        'url' => $appUrl . '/api/reports/income-statement',
                        'type' => 'PDF',
                        'icon' => 'heroicon-o-document-chart-bar',
                        'color' => 'primary',
                    ],
                    [
                        'name' => 'Dividend History',
                        'description' => 'Payouts and declarations.',
                        'url' => $appUrl . '/api/dividends-report',
                        'type' => 'API',
                        'icon' => 'heroicon-o-chart-pie',
                        'color' => 'indigo',
                    ],
                    [
                        'name' => 'Write-Off Accounts',
                        'description' => 'Bad debt and write-off list.',
                        'url' => $baseUrl . '/write-off',
                        'type' => 'API',
                        'icon' => 'heroicon-o-shield-exclamation',
                        'color' => 'danger',
                    ],
                ],
            ],
            'administrative' => [
                'label' => 'Administrative',
                'description' => 'System exports and admin data.',
                'icon' => 'heroicon-o-clipboard-document-list',
                'color' => 'warning',
                'reports' => [
                    [
                        'name' => 'Borrowing Export',
                        'description' => 'Borrowing data (Excel).',
                        'url' => $exportUrl . '/saving-report',
                        'type' => 'Excel',
                        'icon' => 'heroicon-o-arrow-down-tray',
                        'color' => 'success',
                    ],
                    [
                        'name' => 'Capital Shares Export',
                        'description' => 'Investor data (Excel).',
                        'url' => $exportUrl . '/capital-report',
                        'type' => 'Excel',
                        'icon' => 'heroicon-o-arrow-down-tray',
                        'color' => 'success',
                    ],
                    [
                        'name' => 'Audit Logs',
                        'description' => 'System activity and audit trails.',
                        'url' => $appUrl . '/admin/activity-logs',
                        'type' => 'API',
                        'icon' => 'heroicon-o-clipboard-document-check',
                        'color' => 'indigo',
                    ],
                ],
            ],
        ];
    }

    public function getReportStats(): array
    {
        $groups = $this->getReportGroups();
        $reports = collect($groups)->flatMap(fn (array $group) => $group['reports'] ?? []);

        $total = $reports->count();
        $api = $reports->where('type', 'API')->count();
        $excel = $reports->where('type', 'Excel')->count();
        $pdf = $reports->where('type', 'PDF')->count();

        return [
            'total' => $total,
            'api' => $api,
            'excel' => $excel,
            'pdf' => $pdf,
        ];
    }
}
