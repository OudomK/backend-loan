<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loan_officers', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_officers', 'employee_id')) {
                $table->foreignId('employee_id')->nullable()->after('id')
                    ->constrained('employees')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_officers', function (Blueprint $table) {
            if (Schema::hasColumn('loan_officers', 'employee_id')) {
                $table->dropForeign(['employee_id']);
                $table->dropColumn('employee_id');
            }
        });
    }
};
