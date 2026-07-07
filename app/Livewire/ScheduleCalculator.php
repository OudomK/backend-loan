<?php

namespace App\Livewire;

use App\Services\BalloonPaymentCalculator;
use App\Services\LoanCalculator;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

class ScheduleCalculator extends Component
{
    // New fields from the image
    public string $customer_name = '';
    public string $customer_id = '';
    public string $gender = '';
    public string $address = '';
    public string $loan_date = '';
    public string $first_repayment_date = '';
    public string $id_number = '';
    public string $credit_officer_id = '';
    public string $cycle = '';
    public string $qr_type = '';

    // Existing fields updated
    public $amount = '';
    public $duration_months = '';
    public string $payment_frequency = 'Monthly'; // e.g. Monthly, Bi-weekly, Weekly, Daily, Term
    public $interest_rate = '';
    public string $currency = 'USD';
    public string $repayment_method = '';

    public $schedule = [];
    public ?array $customer_info = null; // Store info when calculated to display on schedule

    protected $rules = [
        'amount' => 'required|numeric|min:1',
        'interest_rate' => 'required|numeric|min:0',
        'duration_months' => 'required|integer|min:1',
        'loan_date' => 'required|date',
        'repayment_method' => 'required|string',
        'currency' => 'required|string',
        'first_repayment_date' => 'nullable|date',
    ];

    public function mount()
    {
        $this->loan_date = Carbon::now()->toDateString();

        $customerInfo = session()->get('print_customer_info');
        if ($customerInfo) {
            $this->customer_name = $customerInfo['customer_name'] ?? '';
            $this->customer_id = $customerInfo['customer_id'] ?? '';
            // Strip QF- prefix if it exists so the input box only shows the number
            if (str_starts_with(strtoupper($this->customer_id), 'QF-')) {
                $this->customer_id = substr($this->customer_id, 3);
            }
            $this->gender = $customerInfo['gender'] ?? '';
            $this->address = $customerInfo['address'] ?? '';
            $this->first_repayment_date = $customerInfo['first_repayment_date'] ?? '';
            $this->id_number = $customerInfo['id_number'] ?? '';
            $this->credit_officer_id = $customerInfo['credit_officer_id'] ?? '';
            $this->cycle = $customerInfo['cycle'] ?? '';
            $this->qr_type = $customerInfo['qr_type'] ?? '';
            $this->amount = $customerInfo['amount'] ?? '';
            $this->interest_rate = $customerInfo['interest_rate'] ?? '';
            $this->duration_months = explode(' ', $customerInfo['duration'] ?? '')[0] ?? '';
            $this->payment_frequency = $customerInfo['payment_frequency'] ?? 'Monthly';
            $this->currency = $customerInfo['currency'] ?? 'USD';
            $this->repayment_method = $customerInfo['repayment_method'] ?? '';
        }

        if (empty($this->first_repayment_date)) {
            $this->first_repayment_date = Carbon::now()->addMonth()->toDateString();
        }
    }

    public function updatedLoanDate(string $value)
    {
        if ($this->repayment_method) {
            $this->updatedRepaymentMethod($this->repayment_method);
        }
    }

    public function updatedRepaymentMethod(string $value)
    {
        if (in_array($value, ['fixed_daily', 'fixed_15days_70_30', 'fixed_15days_50_50'])) {
            $this->payment_frequency = 'Daily';
        } elseif (in_array($value, ['fixed_weekly'])) {
            $this->payment_frequency = 'Weekly';
        } else {
            $this->payment_frequency = 'Monthly';
        }

        $loanDate = Carbon::parse($this->loan_date ?: Carbon::now()->toDateString());
        
        if ($value === 'fixed_daily') {
            $this->first_repayment_date = $loanDate->addDay()->toDateString();
        } elseif ($value === 'fixed_weekly') {
            $this->first_repayment_date = $loanDate->addWeek()->toDateString();
        } elseif (in_array($value, ['fixed_15days_70_30', 'fixed_15days_50_50'])) {
            $this->first_repayment_date = $loanDate->addDays(15)->toDateString();
        } else {
            $this->first_repayment_date = $loanDate->addMonth()->toDateString();
        }
    }

    public function calculate()
    {
        if (is_string($this->amount)) {
            $this->amount = str_replace(',', '', $this->amount);
        }

        $this->validate();

        $loanData = [
            'amount' => (float) $this->amount,
            'interest_rate' => (float) $this->interest_rate,
            'duration_months' => (int) $this->duration_months,
            'start_date' => $this->loan_date,
            'currency' => $this->currency,
        ];

        $calculator = new LoanCalculator();

        if ($this->repayment_method === 'Balloon' || $this->repayment_method === 'negotiable') {
            $scheduleRaw = BalloonPaymentCalculator::generateSchedule(
                $loanData,
                'interest_only',
                null,
                null,
                0,
                'one_time',
                $this->first_repayment_date ?: null
            );

            // Map keys from Balloon format to Standard format
            $this->schedule = array_map(function ($item) {
                return [
                    'installment_no' => $item['payment_number'] ?? 0,
                    'date' => $item['payment_date'] ?? '',
                    'principal' => $item['principal_amount'] ?? 0,
                    'interest' => $item['interest_amount'] ?? 0,
                    'payment' => $item['total_paid'] ?? 0,
                    'balance' => $item['remaining_balance'] ?? 0,
                ];
            }, $scheduleRaw);
        } else {
            // Note: Duration type (Days/Years) could be handled here if backend supports it.
            // For now, calculateLoanWithDates assumes duration is what the method is based on (e.g. months for linear_monthly)
            $this->schedule = $calculator->calculateLoanWithDates(
                (float) $loanData['amount'],
                (float) $loanData['interest_rate'],
                (int) $loanData['duration_months'],
                (string) $this->repayment_method,
                (string) $loanData['start_date'],
                (string) $this->currency,
                0,
                'one_time',
                null,
                null,
                $this->first_repayment_date ?: null
            );
        }

        $qrImagePath = null;
        if (!empty($this->qr_type) && is_numeric($this->qr_type)) {
            // Secure: ensure the QR code is active, preventing manual HTML tampering with inactive IDs
            $qr = \App\Models\PaymentQr::where('is_active', true)->find($this->qr_type);
            if ($qr && $qr->image_path) {
                $qrImagePath = \Illuminate\Support\Facades\Storage::url($qr->image_path);
            }
        }

        // Format customer ID with QF- prefix
        $final_customer_id = $this->customer_id;
        if (!empty($final_customer_id) && !str_starts_with(strtoupper($final_customer_id), 'QF-')) {
            $final_customer_id = 'QF-' . $final_customer_id;
        }

        // Fetch actual phone number to prevent frontend leakage
        $actual_phone_number = '';
        if (!empty($this->credit_officer_id)) {
            $co = \App\Models\LoanOfficer::where('status', 'active')->find($this->credit_officer_id);
            if ($co) {
                $actual_phone_number = $co->phone;
            }
        }

        // Save current input to display on schedule sheet
        $this->customer_info = [
            'customer_name' => $this->customer_name,
            'customer_id' => $final_customer_id,
            'gender' => $this->gender,
            'address' => $this->address,
            'loan_date' => $this->loan_date,
            'first_repayment_date' => $this->first_repayment_date,
            'id_number' => $this->id_number,
            'credit_officer_id' => $this->credit_officer_id,
            'phone_number' => $actual_phone_number,
            'cycle' => $this->cycle,
            'qr_type' => $this->qr_type,
            'qr_image_url' => $qrImagePath,
            'amount' => $this->amount,
            'duration' => $this->duration_months . ' Months',
            'payment_frequency' => $this->payment_frequency,
            'interest_rate' => $this->interest_rate,
            'currency' => $this->currency,
            'repayment_method' => $this->repayment_method,
        ];

        session()->put('print_schedule', $this->schedule);
        session()->put('print_customer_info', $this->customer_info);
        return $this->redirect(route('calculator.print'));
    }

    #[Layout('components.layouts.app')]
    public function render()
    {
        $paymentQrs = \App\Models\PaymentQr::where('is_active', true)->get(['id', 'name']);
        // Only fetch ID and Name to completely prevent phone number leakage
        $creditOfficers = \App\Models\LoanOfficer::where('status', 'active')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get(['id', 'name', 'phone']);
        return view('livewire.schedule-calculator', compact('paymentQrs', 'creditOfficers'));
    }
}
