<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loan_officers', function (Blueprint $row) {
            $row->date('start_date')->nullable();
            $row->decimal('max_loan_amount', 15, 2)->nullable();
            $row->string('gender')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_officers', function (Blueprint $row) {
            $row->dropColumn(['start_date', 'max_loan_amount', 'gender']);
        });
    }
};
