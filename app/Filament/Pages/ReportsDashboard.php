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
        $baseUrl = config('app.url') . '/api/reports';
        $exportUrl = config('app.url') . '/api/export';

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
                        'color' => 'success',
                    ],
                    [
                        'name' => 'Disbursements',
                        'description' => 'Funds released history.',
                        'url' => $baseUrl . '/disbursement',
                        'type' => 'API',
                        'color' => 'primary',
                    ],
                    [
                        'name' => 'Arrear (All)',
                        'description' => 'Full overdue payments list.',
                        'url' => $baseUrl . '/arrear-all',
                        'type' => 'API',
                        'color' => 'danger',
                    ],
                    [
                        'name' => 'Arrear (< 30 Days)',
                        'description' => 'Early-stage late payments.',
                        'url' => $baseUrl . '/arrear-under-30',
                        'type' => 'API',
                        'color' => 'warning',
                    ],
                    [
                        'name' => 'Repayment Collection',
                        'description' => 'All collected repayments.',
                        'url' => $baseUrl . '/repayment',
                        'type' => 'API',
                        'color' => 'success',
                    ],
                    [
                        'name' => 'Inactive Loans',
                        'description' => 'Settled or closed accounts.',
                        'url' => $baseUrl . '/inactive-loan',
                        'type' => 'API',
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
                        'color' => 'primary',
                    ],
                    [
                        'name' => 'Income Statement',
                        'description' => 'Profit & Loss (P&L).',
                        'url' => config('app.url') . '/api/reports/income-statement',
                        'type' => 'PDF',
                        'color' => 'primary',
                    ],
                    [
                        'name' => 'Dividend History',
                        'description' => 'Payouts and declarations.',
                        'url' => config('app.url') . '/api/dividends-report',
                        'type' => 'API',
                        'color' => 'indigo',
                    ],
                    [
                        'name' => 'Write-Off Accounts',
                        'description' => 'Bad debt and write-off list.',
                        'url' => $baseUrl . '/write-off',
                        'type' => 'API',
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
                        'name' => 'Savings Export',
                        'description' => 'Savings data (Excel).',
                        'url' => $exportUrl . '/saving-report',
                        'type' => 'Excel',
                        'color' => 'success',
                    ],
                    [
                        'name' => 'Capital Shares Export',
                        'description' => 'Investor data (Excel).',
                        'url' => $exportUrl . '/capital-report',
                        'type' => 'Excel',
                        'color' => 'success',
                    ],
                    [
                        'name' => 'Audit Logs',
                        'description' => 'System activity and audit trails.',
                        'url' => config('app.url') . '/admin/activity-logs',
                        'type' => 'API',
                        'color' => 'indigo',
                    ],
                ],
            ],
        ];
    }
}
