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
        Schema::table('savers', function (Blueprint $table) {
            $table->string('customer_type')->default('Saver')->after('status');
        });

        Schema::table('investors', function (Blueprint $table) {
            $table->string('customer_type')->default('Investor')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('savers', function (Blueprint $table) {
            $table->dropColumn('customer_type');
        });

        Schema::table('investors', function (Blueprint $table) {
            $table->dropColumn('customer_type');
        });
    }
};
