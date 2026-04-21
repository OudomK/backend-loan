<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lenders', function (Blueprint $table) {
            $table->unsignedBigInteger('investor_id')->nullable()->after('id');
            // We don't use foreignId constrained here to avoid strict dependency if investors are deleted,
            // or we could use it depending on preference. Given the current setup, index is enough.
            $table->index('investor_id');
        });
    }

    public function down(): void
    {
        Schema::table('lenders', function (Blueprint $table) {
            $table->dropColumn('investor_id');
        });
    }
};
