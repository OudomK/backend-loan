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
        Schema::table('capital_shares', function (Blueprint $table) {
            $table->decimal('share_qty', 15, 4)->change();
        });

        Schema::table('capital_share_transactions', function (Blueprint $table) {
            $table->decimal('share_qty', 15, 4)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('capital_shares', function (Blueprint $table) {
            $table->integer('share_qty')->change();
        });

        Schema::table('capital_share_transactions', function (Blueprint $table) {
            $table->integer('share_qty')->change();
        });
    }
};
