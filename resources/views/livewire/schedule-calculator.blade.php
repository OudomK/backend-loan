<div
    class="min-h-screen bg-slate-50 dark:bg-slate-900 py-6 px-4 sm:px-6 lg:px-8 font-sans transition-colors duration-300">
    <div class="max-w-7xl mx-auto space-y-6 relative">

        <!-- Dark Mode Toggle -->
        <div class="absolute top-0 right-0">
            <button id="theme-toggle" type="button"
                class="text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-200 dark:focus:ring-slate-700 rounded-lg text-sm p-2.5 transition-colors">
                <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                    xmlns="http://www.w3.org/2000/svg">
                    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                </svg>
                <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"
                    xmlns="http://www.w3.org/2000/svg">
                    <path
                        d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"
                        fill-rule="evenodd" clip-rule="evenodd"></path>
                </svg>
            </button>
        </div>

        <!-- Header -->
        <div class="text-center pt-2 sm:pt-0">
            <h1
                class="text-xl font-bold text-slate-900 tracking-tight sm:text-2xl bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-indigo-600 dark:from-blue-400 dark:to-indigo-400"
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
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">លេខសម្គាល់អតិថិជន (QF)</label>
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
                            <input type="date" wire:model.defer="loan_date" id="loan_date"
                                class="block w-full sm:text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:text-white transition-colors py-2.5 border px-3"
                                required>
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
                                    <option value="{{ $co->id }}">{{ $co->name }} ({{ $co->phone }})</option>
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
                            <label for="qr_type"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ប្រភេទ QR</label>
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
                            <label
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">រយៈពេល</label>
                            <div class="flex space-x-2">
                                <input type="number" wire:model.defer="duration_months" id="duration_months"
                                    class="block w-2/3 sm:text-sm border-slate-300 dark:border-slate-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-900 dark:text-white transition-colors py-2.5 border px-3"
                                    required>
                                <select wire:model.defer="duration_type"
                                    class="block w-1/3 py-2.5 px-2 text-base border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-lg border bg-slate-50 dark:bg-slate-800 dark:text-white">
                                    <option value="Months">ខែ</option>
                                    <option value="Weeks">សប្តាហ៍</option>
                                    <option value="Days">ថ្ងៃ</option>
                                    <option value="Years">ឆ្នាំ</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="interest_rate"
                                class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">អត្រាការប្រាក់ (%)</label>
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
                                    if (v === 'fixed_daily' || v.includes('15days')) {
                                        $wire.set('duration_type', 'Days');
                                    } else if (v.includes('weekly')) {
                                        $wire.set('duration_type', 'Weeks');
                                    } else {
                                        $wire.set('duration_type', 'Months');
                                    }
                                "
                                class="block w-full py-2.5 px-3 text-base border-slate-300 dark:border-slate-600 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-lg border bg-white dark:bg-slate-900 dark:text-white"
                                required>
                                <option value="">ជ្រើសរើសវិធីសាស្ត្របង់ប្រាក់</option>
                                <optgroup label="រយៈពេលខ្លី">
                                    <option value="fixed_daily">បង់ថេរប្រចាំថ្ងៃ</option>
                                    <option value="fixed_weekly">បង់ថេរប្រចាំសប្តាហ៍</option>
                                    <option value="fixed_biweekly">បង់ថេរ២សប្តាហ៍ម្តង</option>
                                </optgroup>
                                <optgroup label="ប្រចាំខែ">
                                    <option value="fixed_monthly">បង់ថេរប្រចាំខែ (Flat)</option>
                                    <option value="linear_monthly">បង់ថយប្រចាំខែ (Declining)</option>
                                    <option value="annuity_monthly">បង់ថេរប្រចាំខែ (Annuity)</option>
                                </optgroup>
                                <optgroup label="វដ្ត ១៥ថ្ងៃ">
                                    <option value="fixed_15days_70_30">បង់ថេរ ១៥ថ្ងៃ (70/30)</option>
                                    <option value="fixed_15days_50_50">បង់ថេរ ១៥ថ្ងៃ (50/50)</option>
                                </optgroup>
                                <optgroup label="ពិសេស">
                                    <option value="Balloon">Balloon</option>
                                    <option value="negotiable">អាចចរចាបាន</option>
                                </optgroup>
                            </select>
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
