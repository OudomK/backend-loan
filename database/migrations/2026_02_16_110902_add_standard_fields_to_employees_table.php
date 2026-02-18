<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // 1. Identity & Personal Info
            $table->string('name_kh')->nullable()->after('name');
            $table->string('employee_code')->nullable()->unique()->after('id'); // Internal ID
            $table->string('photo')->nullable()->after('gender');
            $table->string('id_card_number')->nullable()->after('dob');
            $table->string('marital_status')->nullable()->after('gender'); // Single, Married, etc.
            $table->integer('number_of_children')->default(0)->after('marital_status');

            // 2. Employment Details
            $table->string('employment_type')->default('Full-time')->after('position_id'); // Full-time, Probation, etc.
            $table->date('contract_end_date')->nullable()->after('date_joined');
            $table->integer('working_days_per_week')->default(5)->after('employment_type');

            // 3. Banking & Compliance
            $table->string('bank_name')->nullable()->after('salary');
            $table->string('bank_account_number')->nullable()->after('bank_name');
            $table->string('nssf_id')->nullable()->after('bank_account_number');

            // 4. Emergency Contact
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'name_kh',
                'employee_code',
                'photo',
                'id_card_number',
                'marital_status',
                'number_of_children',
                'employment_type',
                'contract_end_date',
                'working_days_per_week',
                'bank_name',
                'bank_account_number',
                'nssf_id',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]);
        });
    }
};
