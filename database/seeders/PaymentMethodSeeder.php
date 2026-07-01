<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = ['Cash', 'Bank Transfer', 'Cheque'];

        foreach ($methods as $method) {
            \App\Models\PaymentMethod::firstOrCreate(['name' => $method]);
        }
    }
}
