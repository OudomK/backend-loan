<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->unsignedTinyInteger('pay_day_1')->nullable()->after('payment_frequency');
            $table->unsignedTinyInteger('pay_day_2')->nullable()->after('pay_day_1');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table): void {
            $table->dropColumn(['pay_day_1', 'pay_day_2']);
        });
    }
};
