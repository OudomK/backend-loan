<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('miscellaneous_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('miscellaneous_transactions', 'expense_category_id')) {
                $table->foreignId('expense_category_id')
                    ->nullable()
                    ->after('type')
                    ->constrained('expense_categories')
                    ->nullOnDelete();
            }
        });

        $categories = DB::table('expense_categories')->pluck('id', 'name');

        foreach ($categories as $name => $id) {
            DB::table('miscellaneous_transactions')
                ->where('type', 'expense')
                ->where('category', $name)
                ->whereNull('expense_category_id')
                ->update(['expense_category_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('miscellaneous_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('miscellaneous_transactions', 'expense_category_id')) {
                $table->dropConstrainedForeignId('expense_category_id');
            }
        });
    }
};
