<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_officers', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_officers', 'phone_2')) {
                $table->string('phone_2')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('loan_officers', 'phone_3')) {
                $table->string('phone_3')->nullable()->after('phone_2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_officers', function (Blueprint $table) {
            if (Schema::hasColumn('loan_officers', 'phone_3')) {
                $table->dropColumn('phone_3');
            }

            if (Schema::hasColumn('loan_officers', 'phone_2')) {
                $table->dropColumn('phone_2');
            }
        });
    }
};
