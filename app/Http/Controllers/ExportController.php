<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use App\Models\SavingAccount;
use App\Models\CapitalShare;

class ExportController extends Controller
{
    public function exportSavingReport()
    {
        // Fetch data
        $accounts = SavingAccount::with(['borrower', 'transactions'])
            ->orderBy('created_at', 'desc')
            ->get();

        $reportData = $accounts->map(function ($account) {
            $deposits = $account->transactions->where('transaction_type', 'Deposit');
            $withdrawals = $account->transactions->where('transaction_type', 'Withdrawal');
            $interests = $account->transactions->where('transaction_type', 'Interest');
            $lastTrans = $account->transactions->sortByDesc('transaction_date')->first();

            return [
                'account_id' => $account->id,
                'created_at' => $account->created_at->format('Y-m-d'),
                'account_number' => $account->account_number,
                'saver_code' => $account->borrower->borrower_code ?? '-',
                'saver_name' => $account->borrower
                    ? trim($account->borrower->first_name . ' ' . $account->borrower->last_name)
                    : 'Unknown',
                'account_type' => $account->account_type,
                'currency' => $account->currency,
                'term' => $account->term ?? '-',
                'maturity_date' => $account->maturity_date ?? '-',
                'opening_balance' => $deposits->first()->amount ?? 0,
                'current_balance' => $account->balance,
                'interest_rate' => $account->interest_rate,
                'total_deposits' => $deposits->sum('amount'),
                'total_withdrawals' => $withdrawals->sum('amount'),
                'principal' => $deposits->first()->amount ?? 0, // Same as opening for simplicity
                'interest' => $interests->sum('amount'),
                'last_transaction_date' => $lastTrans->transaction_date ?? '-',
                'status' => $account->status,
            ];
        });

        // Create Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = [
            'No',
            'Date Opened',
            'Account No',
            'Customer Code',
            'Customer Name',
            'Account Type',
            'Currency',
            'Term',
            'Maturity Date',
            'Opening Balance',
            'Current Balance',
            'Interest Rate',
            'Total Deposits',
            'Total Withdrawals',
            'Principal',
            'Interest',
            'Last Transaction',
            'Status'
        ];

        // Write headers
        $sheet->fromArray($headers, null, 'A1');

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A1:R1')->applyFromArray($headerStyle);

        // Write data
        $row = 2;
        foreach ($reportData as $index => $data) {
            $sheet->fromArray([
                $index + 1,
                $data['created_at'],
                $data['account_number'],
                $data['saver_code'],
                $data['saver_name'],
                $data['account_type'],
                $data['currency'],
                $data['term'],
                $data['maturity_date'],
                $data['opening_balance'],
                $data['current_balance'],
                $data['interest_rate'] . '%',
                $data['total_deposits'],
                $data['total_withdrawals'],
                $data['principal'],
                $data['interest'],
                $data['last_transaction_date'],
                $data['status'],
            ], null, "A{$row}");
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'R') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Download
        $writer = new Xlsx($spreadsheet);
        $filename = 'saving_report_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    public function exportCapitalReport()
    {
        // Fetch data
        $shares = CapitalShare::with('borrower')
            ->orderBy('purchase_date', 'desc')
            ->get();

        $reportData = $shares->map(function ($share) {
            return [
                'share_id' => $share->id,
                'purchase_date' => $share->purchase_date,
                'certificate_no' => $share->certificate_no,
                'holder_id' => $share->holder_id,
                'holder_name' => $share->borrower
                    ? trim($share->borrower->first_name . ' ' . $share->borrower->last_name)
                    : 'Unknown',
                'share_qty' => $share->share_qty,
                'par_value' => $share->par_value,
                'total_capital' => $share->total_capital,
                'currency' => $share->currency,
                'dividends' => 0, // Placeholder - calculate from dividend transactions
                'last_dividend_date' => '-', // Placeholder
                'status' => $share->status,
            ];
        });

        // Create Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers (No Principal/Interest, has Dividends)
        $headers = [
            'No',
            'Purchase Date',
            'Certificate No',
            'Holder ID',
            'Holder Name',
            'Share Quantity',
            'Par Value',
            'Total Capital',
            'Currency',
            'Dividends',
            'Last Dividend Date',
            'Status'
        ];

        // Write headers
        $sheet->fromArray($headers, null, 'A1');

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '70AD47']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);

        // Write data
        $row = 2;
        foreach ($reportData as $index => $data) {
            $sheet->fromArray([
                $index + 1,
                $data['purchase_date'],
                $data['certificate_no'],
                $data['holder_id'],
                $data['holder_name'],
                $data['share_qty'],
                $data['par_value'],
                $data['total_capital'],
                $data['currency'],
                $data['dividends'],
                $data['last_dividend_date'],
                $data['status'],
            ], null, "A{$row}");
            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Download
        $writer = new Xlsx($spreadsheet);
        $filename = 'capital_share_report_' . date('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment;filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }
}
