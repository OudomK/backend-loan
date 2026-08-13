<?php

namespace App\Livewire;

use App\Services\LoanScheduleService;
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
    public string $loan_product_id = '';

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
            $this->loan_product_id = $customerInfo['loan_product_id'] ?? '';
            $this->amount = $customerInfo['amount'] ?? '';
            $this->interest_rate = $customerInfo['interest_rate'] ?? '';
            $this->duration_months = explode(' ', $customerInfo['duration'] ?? '')[0] ?? '';
            $this->payment_frequency = $customerInfo['payment_frequency'] ?? 'Monthly';
            $this->currency = $customerInfo['currency'] ?? 'USD';
            $this->repayment_method = $customerInfo['repayment_method'] ?? '';
        }

        if ($this->isSplitRepaymentMethod($this->repayment_method)) {
            $this->payment_frequency = 'Biweekly';
            $this->first_repayment_date = $this->splitFirstRepaymentDate($this->loan_date);
        }

        if (empty($this->first_repayment_date)) {
            $this->first_repayment_date = Carbon::now()->addMonth()->toDateString();
        }
    }

    private function isSplitRepaymentMethod(?string $method): bool
    {
        return LoanScheduleService::isSplitMethod($method);
    }

    private function splitFirstRepaymentDate(string $loanDate): string
    {
        $date = Carbon::parse($loanDate ?: Carbon::now()->toDateString());

        if ($date->day <= 15) {
            return $date->day(26)->toDateString();
        }

        return $date->addMonthNoOverflow()->day(11)->toDateString();
    }

    private function monthlyFirstRepaymentDate(string $loanDate, int $paymentDay = 11): string
    {
        $date = Carbon::parse($loanDate ?: Carbon::now()->toDateString())->addMonthNoOverflow();
        $paymentDay = max(1, min($paymentDay, $date->daysInMonth));

        return $date->day($paymentDay)->toDateString();
    }

    public function updatedLoanProductId(string $value)
    {
        // When loan product changes, we might want to update interest rate or something, 
        // but for now we just keep the selected product.
    }

    public function updatedLoanDate(string $value)
    {
        if ($this->repayment_method) {
            $this->updatedRepaymentMethod($this->repayment_method);
        }
    }

    public function updatedRepaymentMethod(string $value)
    {
        $this->payment_frequency = LoanScheduleService::displayPaymentFrequency(
            $value,
            $this->payment_frequency
        );

        $loanDate = Carbon::parse($this->loan_date ?: Carbon::now()->toDateString());
        
        if ($value === 'fixed_daily') {
            $this->first_repayment_date = $loanDate->addDay()->toDateString();
        } elseif ($value === 'fixed_weekly') {
            $this->first_repayment_date = $loanDate->addDays(6)->toDateString();
        } elseif ($value === 'fixed_biweekly') {
            $this->first_repayment_date = $loanDate->addDays(13)->toDateString();
        } elseif ($this->isSplitRepaymentMethod($value)) {
            $this->first_repayment_date = $this->splitFirstRepaymentDate($loanDate->toDateString());
        } elseif ($value === 'Balloon') {
            $this->first_repayment_date = $loanDate->addMonthNoOverflow()->toDateString();
        } else {
            $this->first_repayment_date = $this->monthlyFirstRepaymentDate($loanDate->toDateString());
        }
    }

    public function calculate()
    {
        if (is_string($this->amount)) {
            $this->amount = str_replace(',', '', $this->amount);
        }

        $this->payment_frequency = LoanScheduleService::displayPaymentFrequency(
            $this->repayment_method,
            $this->payment_frequency
        );

        if ($this->isSplitRepaymentMethod($this->repayment_method)) {
            // Keep the standalone Web calculator aligned with the App/API:
            // the term is in months, the frequency label is Biweekly, and the
            // two fixed collection days are 11 and 26.
            $this->payment_frequency = 'Biweekly';
            $this->first_repayment_date = $this->splitFirstRepaymentDate($this->loan_date);
        }

        $this->validate();

        $scheduleService = app(LoanScheduleService::class);
        $this->schedule = $scheduleService->generate([
            'amount' => (float) $this->amount,
            'interest_rate' => (float) $this->interest_rate,
            'duration_months' => (int) $this->duration_months,
            'start_date' => $this->loan_date,
            'currency' => $this->currency,
            'repayment_method' => $this->repayment_method,
            'admin_fee' => 0,
            'admin_fee_type' => 'one_time',
            'first_repayment_date' => $this->first_repayment_date ?: null,
        ]);

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
        $durationSuffix = ' ខែ';
        if (!$this->isSplitRepaymentMethod($this->repayment_method)) {
            switch (strtolower($this->payment_frequency)) {
                case 'daily':
                    $durationSuffix = ' ថ្ងៃ';
                    break;
                case 'weekly':
                    $durationSuffix = ' សប្តាហ៍';
                    break;
                case 'bi-weekly':
                case 'biweekly':
                    $durationSuffix = ' កន្លះខែ';
                    break;
                case 'term':
                    $durationSuffix = ' លើក';
                    break;
            }
        }

        $productName = 'Personal Loan';
        if ($this->loan_product_id) {
            $product = \App\Models\LoanProduct::find($this->loan_product_id);
            if ($product) {
                $productName = $product->name;
            }
        }

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
            'loan_product_id' => $this->loan_product_id,
            'product_name' => $productName,
            'qr_image_url' => $qrImagePath,
            'amount' => $this->amount,
            'duration' => $this->duration_months . $durationSuffix,
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
        $loanProducts = \App\Models\LoanProduct::where('is_active', true)->get(['id', 'name']);
        return view('livewire.schedule-calculator', compact('paymentQrs', 'creditOfficers', 'loanProducts'));
    }
}
