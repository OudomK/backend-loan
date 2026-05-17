<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('group_name', 100)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('expense_categories')->insert([
            [
                'name' => 'Office Rental Expense',
                'group_name' => 'Administrative Expenses',
                'description' => 'Office rent and occupancy costs.',
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Utilities Expense',
                'group_name' => 'Administrative Expenses',
                'description' => 'Electricity, water, and utility charges.',
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Internet & Telephone Expense',
                'group_name' => 'Administrative Expenses',
                'description' => 'Communication service charges.',
                'is_active' => true,
                'sort_order' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Fuel & Transportation Expense',
                'group_name' => 'Operating Expenses',
                'description' => 'Travel, fuel, and local transportation costs.',
                'is_active' => true,
                'sort_order' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Marketing Expense',
                'group_name' => 'Selling & Marketing Expenses',
                'description' => 'Promotion and marketing campaign costs.',
                'is_active' => true,
                'sort_order' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Maintenance Expense',
                'group_name' => 'Operating Expenses',
                'description' => 'Repairs and maintenance costs.',
                'is_active' => true,
                'sort_order' => 60,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Office Supplies Expense',
                'group_name' => 'Administrative Expenses',
                'description' => 'Stationery and office consumables.',
                'is_active' => true,
                'sort_order' => 70,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Depreciation Expense',
                'group_name' => 'Operating Expenses',
                'description' => 'Depreciation on fixed assets.',
                'is_active' => true,
                'sort_order' => 80,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Software Subscription Expense',
                'group_name' => 'Administrative Expenses',
                'description' => 'Software and system subscription costs.',
                'is_active' => true,
                'sort_order' => 90,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Professional Service Expense',
                'group_name' => 'Administrative Expenses',
                'description' => 'Professional and consulting fees.',
                'is_active' => true,
                'sort_order' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bank Charge Expense',
                'group_name' => 'Finance Costs',
                'description' => 'Bank fees and transaction charges.',
                'is_active' => true,
                'sort_order' => 110,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Training Expense',
                'group_name' => 'Administrative Expenses',
                'description' => 'Staff training and development costs.',
                'is_active' => true,
                'sort_order' => 120,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Other Administrative Expense',
                'group_name' => 'Administrative Expenses',
                'description' => 'Administrative expenses that do not fit another standard category.',
                'is_active' => true,
                'sort_order' => 130,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Rental Photo Stage Expense',
                'group_name' => 'Other Operating Expenses',
                'description' => 'Special project or occasional rental expense.',
                'is_active' => true,
                'sort_order' => 140,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Miscellaneous Expense',
                'group_name' => 'Miscellaneous Expense',
                'description' => 'Small, rare, uncategorized expense items.',
                'is_active' => true,
                'sort_order' => 150,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
