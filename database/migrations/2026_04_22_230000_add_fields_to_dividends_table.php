<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dividends', function (Blueprint $table) {
            $table->enum('distribution_basis', ['total', 'per_share'])
                ->default('total')
                ->after('currency');
            $table->date('payment_date')->nullable()->after('declared_date');
            $table->foreignId('declared_by')
                ->nullable()
                ->after('payment_date')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable()->after('declared_by');
            $table->decimal('tax_amount', 15, 2)->default(0)->after('notes');
            $table->decimal('net_amount', 15, 2)->default(0)->after('tax_amount');
        });

        DB::table('dividends')->update([
            'distribution_basis' => 'total',
            'payment_date' => DB::raw('declared_date'),
            'tax_amount' => 0,
            'net_amount' => DB::raw('total_amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('dividends', function (Blueprint $table) {
            $table->dropConstrainedForeignId('declared_by');
            $table->dropColumn([
                'distribution_basis',
                'payment_date',
                'notes',
                'tax_amount',
                'net_amount',
            ]);
        });
    }
};

