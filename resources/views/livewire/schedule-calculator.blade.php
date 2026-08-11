<div
    class="min-h-screen bg-slate-50 dark:bg-slate-900 py-6 px-4 sm:px-6 lg:px-8 font-sans transition-colors duration-300">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <div class="max-w-7xl mx-auto space-y-6 relative">

        <!-- Dark Mode Toggle -->
        <div class="absolute top-0 right-0">
            <button type="button" x-data="{
                    theme: localStorage.getItem('color-theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'),
                    toggle() {
                        this.theme = this.theme === 'dark' ? 'light' : 'dark';
                        localStorage.setItem('color-theme', this.theme);
                        if (this.theme === 'dark') {
                            document.documentElement.classList.add('dark');
                        } else {
                            document.documentElement.classList.remove('dark');
                        }
                    }
                }" x-on:click="toggle()"
                class="text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-200 dark:focus:ring-slate-700 rounded-lg text-sm p-2.5 transition-colors">
                <svg x-show="theme !== 'dark'" style="display: none;" class="w-5 h-5" fill="currentColor"
                    viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                </svg>
                <svg x-show="theme === 'dark'" style="display: none;" class="w-5 h-5" fill="currentColor"
                    viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"
                        fill-rule="evenodd" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>

        <!-- Header -->
        <div class="text-center pt-2 sm:pt-0">
            <h1 class="text-xl font-bold text-slate-900 tracking-tight sm:text-2xl bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-blue-400 dark:to-indigo-400"
                style="font-family: 'Krasar', 'Kantumruy Pro', sans-serif;">
                តារាងគណនាប្រាក់កម្ចី
            </h1>
            <p class="mt-2 text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-2xl mx-auto">
                មើលកាលវិភាគបង់ប្រាក់ជាមុនយ៉ាងរហ័ស ផ្អែកលើលក្ខខណ្ឌកម្ចី និងវិធីសាស្ត្របង់ប្រាក់ផ្សេងៗគ្នា។
            </p>
        </div>

        <div class="flex flex-col gap-8 items-center w-full">

            <!-- Form Section -->
            <div
                class="w-full print:hidden bg-white dark:bg-slate-800 shadow-xl shadow-slate-200/50 dark:shadow-none rounded-2xl p-6 lg:p-8 border border-slate-100 dark:border-slate-700 relative overflow-hidden">
                <div
                    class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-400 to-indigo-500 dark:from-blue-500 dark:to-indigo-600">
                </div>

                <h2 class="text-xl font-semibold text-slate-800 dark:text-white mb-6 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    ព័ត៌មានអតិថិជន និងប្រាក់កម្ចី
                </h2>

                <form wire:submit.prevent="calculate" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

                        <!-- Row 1 -->
                        <div>
                            <label for="customer_name"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ឈ្មោះអតិថិជន</label>
                            <input type="text" wire:model.defer="customer_name" id="customer_name"
                                class="block w-full sm:text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:text-white transition-colors py-2.5 border px-3"
                                placeholder="">
                        </div>
                        <div>
                            <label for="customer_id"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">លេខសម្គាល់អតិថិជន
                                (QF)</label>
                            <div class="flex rounded-lg shadow-sm">
                                <span
                                    class="inline-flex items-center px-3 rounded-l-lg border border-r-0 border-slate-300 bg-slate-100 text-slate-600 sm:text-sm dark:bg-slate-800 dark:border-slate-600 dark:text-slate-400 font-semibold">
                                    QF-
                                </span>
                                <input type="text" wire:model.defer="customer_id" id="customer_id"
                                    class="flex-1 block w-full rounded-none rounded-r-lg sm:text-sm border-slate-300 dark:border-slate-600 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:text-white transition-colors py-2.5 border px-3"
                                    placeholder="001">
                            </div>
                        </div>
                        <div>
                            <label for="gender"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ភេទ</label>
                            <select wire:model.defer="gender" id="gender"
                                class="block w-full py-2.5 px-3 text-base border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-lg border bg-white dark:bg-slate-900 dark:text-white">
                                <option value="">ជ្រើសរើសភេទ</option>
                                <option value="Male">ប្រុស</option>
                                <option value="Female">ស្រី</option>
                            </select>
                        </div>
                        <div>
                            <label for="address"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">អាសយដ្ឋាន</label>
                            <input type="text" wire:model.defer="address" id="address"
                                class="block w-full sm:text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:text-white transition-colors py-2.5 border px-3"
                                placeholder="">
                        </div>

                        <!-- Row 2 -->
                        <div>
                            <label for="loan_date"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">កាលបរិច្ឆេទខ្ចី</label>
                            <div wire:ignore>
                                <input type="text" wire:model.defer="loan_date" id="loan_date" x-data x-init="
                                    flatpickr($el, {
                                        dateFormat: 'Y-m-d',
                                        altInput: true,
                                        altFormat: 'd/m/Y',
                                        defaultDate: '{{ $loan_date }}',
                                        onChange: function(selectedDates, dateStr, instance) {
                                            $el.dispatchEvent(new Event('input', { bubbles: true }));
                                            let v = document.getElementById('repayment_method') ? document.getElementById('repayment_method').value : '';
                                            if (!v || !dateStr) return;
                                            let d = new Date(dateStr);
                                            if (v === 'fixed_daily') d.setDate(d.getDate());
                                            else if (v === 'fixed_weekly') d.setDate(d.getDate() + 6);
                                            else if (v === 'fixed_biweekly') d.setDate(d.getDate() + 13);
                                            else if (v.includes('15days')) {
                                                if (d.getDate() <= 15) d.setDate(26);
                                                else {
                                                    d.setMonth(d.getMonth() + 1);
                                                    d.setDate(11);
                                                }
                                            }
                                            else if (v === 'Balloon') d.setMonth(d.getMonth() + 1);
                                            else {
                                                d.setMonth(d.getMonth() + 1);
                                                d.setDate(11);
                                            }
                                            let formatted = d.toISOString().split('T')[0];
                                            @this.set('first_repayment_date', formatted);
                                            let firstRepaymentEl = document.getElementById('first_repayment_date');
                                            if (firstRepaymentEl && firstRepaymentEl._flatpickr) {
                                                firstRepaymentEl._flatpickr.setDate(formatted);
                                            }
                                        }
                                    })
                                " class="block w-full sm:text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:text-white transition-colors py-2.5 border px-3"
                                    required>
                            </div>
                        </div>
                        <div>
                            <label for="id_number"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">លេខអត្តសញ្ញាណប័ណ្ណ</label>
                            <input type="text" wire:model.defer="id_number" id="id_number"
                                class="block w-full sm:text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:text-white transition-colors py-2.5 border px-3"
                                placeholder="">
                        </div>
                        <div>
                            <label for="credit_officer_id"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">មន្ត្រីឥណទាន</label>
                            <select wire:model.defer="credit_officer_id" id="credit_officer_id"
                                class="block w-full py-2.5 px-3 text-base border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-lg border bg-white dark:bg-slate-900 dark:text-white">
                                <option value="">ជ្រើសរើសមន្ត្រីឥណទាន</option>
                                @foreach($creditOfficers as $co)
                                    <option value="{{ $co->id }}">{{ $co->name }} ({{ $co->phone ? implode(' ', str_split(preg_replace('/[^0-9]/', '', $co->phone), 3)) : '-' }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="cycle"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">វគ្គទី</label>
                            <input type="text" wire:model.defer="cycle" id="cycle"
                                class="block w-full sm:text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:text-white transition-colors py-2.5 border px-3"
                                placeholder="">
                        </div>

                        <!-- Row 3 -->
                        <div>
                            <label for="loan_product_id"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ប្រភេទកម្ចី (Loan Product)</label>
                            <select wire:model.defer="loan_product_id" id="loan_product_id"
                                class="block w-full py-2.5 px-3 text-base border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-lg border bg-white dark:bg-slate-900 dark:text-white">
                                <option value="">ជ្រើសរើសប្រភេទកម្ចី</option>
                                @foreach($loanProducts as $product)
                                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="qr_type"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ប្រភេទ
                                QR</label>
                            <select wire:model.defer="qr_type" id="qr_type"
                                class="block w-full py-2.5 px-3 text-base border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-lg border bg-white dark:bg-slate-900 dark:text-white">
                                <option value="">ជ្រើសរើសប្រភេទ QR</option>
                                @foreach($paymentQrs as $qr)
                                    <option value="{{ $qr->id }}">{{ $qr->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="amount"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ចំនួនទឹកប្រាក់</label>
                            <input type="text"
                                oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',')"
                                wire:model.defer="amount" id="amount"
                                class="block w-full sm:text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:text-white transition-colors py-2.5 border px-3"
                                required>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">រយៈពេល
                                <span id="duration_unit_label">(Months)</span></label>
                            <input type="number" wire:model.defer="duration_months" id="duration_months"
                                class="block w-full sm:text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:text-white transition-colors py-2.5 border px-3"
                                required>
                        </div>
                        <div>
                            <label
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ប្រេកង់បង់ប្រាក់
                                (Payment Freq.)</label>
                            <select wire:model.defer="payment_frequency" disabled
                                class="block w-full py-2.5 px-3 text-base border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-lg border bg-slate-50 dark:bg-slate-800 dark:text-white"
                                required>
                                <option value="Monthly">Monthly</option>
                                <option value="Biweekly">Biweekly</option>
                                <option value="Weekly">Weekly</option>
                                <option value="Daily">Daily</option>
                                <option value="Term">Term</option>
                            </select>
                        </div>
                        <div>
                            <label for="interest_rate"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">អត្រាការប្រាក់
                                (%)</label>
                            <input type="number" wire:model.defer="interest_rate" id="interest_rate"
                                class="block w-full sm:text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:text-white transition-colors py-2.5 border px-3"
                                step="0.01" required>
                        </div>

                        <!-- Row 4 -->
                        <div>
                            <label for="currency"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">រូបិយប័ណ្ណ</label>
                            <select wire:model.defer="currency" id="currency"
                                class="block w-full py-2.5 px-3 text-base border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-lg border bg-white dark:bg-slate-900 dark:text-white"
                                required>
                                <option value="KHR">៛ រៀល (KHR)</option>
                                <option value="USD">$ ដុល្លារ (USD)</option>
                            </select>
                        </div>
                        <div>
                            <label for="repayment_method"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">វិធីសាស្ត្របង់ប្រាក់</label>
                            <select wire:model.defer="repayment_method" id="repayment_method" x-on:change="
                                    let v = $event.target.value;
                                    if (v === 'fixed_daily') {
                                        $wire.set('payment_frequency', 'Daily');
                                    } else if (v === 'fixed_biweekly') {
                                        $wire.set('payment_frequency', 'Biweekly');
                                    } else if (v === 'fixed_weekly') {
                                        $wire.set('payment_frequency', 'Weekly');
                                    } else if (v.includes('15days')) {
                                        $wire.set('payment_frequency', 'Biweekly');
                                    } else if (v === 'negotiable') {
                                        $wire.set('payment_frequency', 'Term');
                                    } else {
                                        $wire.set('payment_frequency', 'Monthly');
                                    }

                                    let durationUnit = '(Months)';
                                    if (v === 'fixed_daily') durationUnit = '(Days)';
                                    else if (v === 'fixed_weekly') durationUnit = '(Weeks)';
                                    else if (v === 'fixed_biweekly') durationUnit = '(Bi-weeks)';
                                    let durationUnitEl = document.getElementById('duration_unit_label');
                                    if (durationUnitEl) durationUnitEl.textContent = durationUnit;
                                    
                                    let loanDateStr = document.getElementById('loan_date') ? document.getElementById('loan_date').value : '';
                                    if (!loanDateStr) {
                                        loanDateStr = new Date().toISOString().split('T')[0];
                                    }
                                    let d = new Date(loanDateStr);
                                    if (v === 'fixed_daily') {
                                        d.setDate(d.getDate());
                                    } else if (v === 'fixed_weekly') {
                                        d.setDate(d.getDate() + 6);
                                    } else if (v === 'fixed_biweekly') {
                                        d.setDate(d.getDate() + 13);
                                    } else if (v.includes('15days')) {
                                        if (d.getDate() <= 15) d.setDate(26);
                                        else {
                                            d.setMonth(d.getMonth() + 1);
                                            d.setDate(11);
                                        }
                                    } else if (v === 'Balloon') {
                                        d.setMonth(d.getMonth() + 1);
                                    } else {
                                        d.setMonth(d.getMonth() + 1);
                                        d.setDate(11);
                                    }
                                    let formatted = d.toISOString().split('T')[0];
                                    $wire.set('first_repayment_date', formatted);
                                    
                                    let firstRepaymentEl = document.getElementById('first_repayment_date');
                                    if (firstRepaymentEl && firstRepaymentEl._flatpickr) {
                                        firstRepaymentEl._flatpickr.setDate(formatted);
                                    }
                                "
                                class="block w-full py-2.5 px-3 text-base border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-lg border bg-white dark:bg-slate-900 dark:text-white"
                                required>
                                <option value="">ជ្រើសរើសវិធីសាស្ត្របង់ប្រាក់</option>
                                <optgroup label="Flat">
                                    <option value="fixed_daily">Daily (ថ្ងៃ)</option>
                                    <option value="fixed_weekly">Weekly (សប្តាហ៍)</option>
                                    <option value="fixed_15days_70_30">Bi-weekly 70-30 (កន្លះខែ)</option>
                                    <option value="fixed_15days_50_50">Bi-weekly 50-50 (កន្លះខែ)</option>
                                    <option value="fixed_monthly">Monthly (បង់ខែ)</option>
                                </optgroup>
                                <optgroup label="&nbsp;">
                                    <option value="annuity_monthly">Annuity (បង់ថេរ)</option>
                                    <option value="linear_monthly">Linear (បង់ថយ)</option>
                                    <option value="Balloon">Balloon (បង់តែការប្រាក់ ប្រាក់ដើមសងចុងវគ្គ)</option>
                                    <option value="negotiable">Negotiable (អាចចរចាបាន)</option>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <label for="first_repayment_date"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">កាលបរិច្ឆេទសងលើកដំបូង</label>
                            <div wire:ignore>
                                <input type="text" wire:model.defer="first_repayment_date" id="first_repayment_date"
                                    x-data x-init="
                                    flatpickr($el, {
                                        dateFormat: 'Y-m-d',
                                        altInput: true,
                                        altFormat: 'd/m/Y',
                                        defaultDate: '{{ $first_repayment_date }}'
                                    })
                                "
                                    class="block w-full sm:text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:text-white transition-colors py-2.5 border px-3">
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-slate-100 dark:border-slate-700 mt-6 flex justify-end">
                        <button type="submit"
                            class="w-full md:w-auto flex justify-center py-2.5 px-8 border border-transparent rounded-lg shadow-md text-sm font-semibold text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-transform duration-150 active:scale-95">
                            <svg wire:loading wire:target="calculate" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            បង្កើតកាលវិភាគ
                        </button>
                    </div>
                </form>
            </div>


        </div>
    </div>
</div>
</div>
