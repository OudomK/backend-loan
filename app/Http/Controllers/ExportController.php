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
        $shares = CapitalShare::with(['lender', 'investor'])
            ->orderBy('id', 'desc')
            ->get();

        $reportData = $shares->map(function ($s) {
            $name = 'N/A';
            $code = '-';
            $type = 'Individual';

            if ($s->investor) {
                $name = trim($s->investor->first_name . ' ' . $s->investor->last_name);
                $code = $s->investor->customer_code;
                $type = $s->investor->customer_type ?? 'Individual';
            } elseif ($s->lender) {
                $name = $s->lender->name;
                $code = $s->lender->lender_code ?? $s->lender->code ?? '-';
                $type = $s->lender->lender_type ?? 'Individual';
            }

            $isReal = $s->category === 'Real Capital';

            return [
                'date' => $s->borrowing_date ?? $s->created_at->format('Y-m-d'),
                'account_no' => $s->account_no,
                'lender_code' => $code,
                'name' => $name,
                'lender_type' => $type,
                'category' => $s->category,
                'payment' => $isReal ? '—' : ($s->payment_method ?? '-'),
                'first_pay_date' => $isReal ? '—' : ($s->first_pay_date ?? '-'),
                'currency' => $s->currency,
                'term' => $isReal ? '—' : ($s->term_months ?? '-'),
                'share' => ($s->share_qty ?? 0) . '%',
                'amount' => (float) $s->amount,
                'rate' => $isReal ? '—' : ($s->interest_rate . '%'),
                'fee' => $isReal ? '—' : (float) $s->fee,
                'maturity' => $isReal ? '—' : ($s->maturity_date ?? '-'),
                'sl_term' => $isReal ? '—' : ($s->sl_term ?? '-'),
                'balance' => $isReal ? '—' : (float) $s->balance,
                'late' => '-',
                'dividends' => $isReal ? (float) $s->dividends : '—',
                'status' => $s->status,
            ];
        });

        // Create Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = [
            'No',
            'Date',
            'Acc Code',
            'Len Code',
            'Name',
            'Len Type',
            'Category',
            'Payment',
            '1st Pay.',
            'Ccy',
            'Term',
            'Share (%)',
            'Amount',
            'Rate',
            'Fee',
            'Maturity',
            'S/L Term',
            'Balance',
            'Late',
            'Dividends',
            'Status'
        ];

        // Write headers
        $sheet->fromArray($headers, null, 'A1');

        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D32']], // Dark Green
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A1:U1')->applyFromArray($headerStyle);

        // Write data
        $row = 2;
        foreach ($reportData as $index => $data) {
            $sheet->fromArray([
                $index + 1,
                $data['date'],
                $data['account_no'],
                $data['lender_code'],
                $data['name'],
                $data['lender_type'],
                $data['category'],
                $data['payment'],
                $data['first_pay_date'],
                $data['currency'],
                $data['term'],
                $data['share'],
                $data['amount'],
                $data['rate'],
                $data['fee'],
                $data['maturity'],
                $data['sl_term'],
                $data['balance'],
                $data['late'],
                $data['dividends'],
                $data['status']
            ], null, "A{$row}");

            // Set alignments
            $sheet->getStyle("A{$row}:D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("J{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("K{$row}:L{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("M{$row}:N{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("O{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'O') as $col) {
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
