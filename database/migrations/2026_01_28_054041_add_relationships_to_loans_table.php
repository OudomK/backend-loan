<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->string('co_borrower_relationship')->nullable()->after('co_borrower_id');
            $table->string('guarantor_relationship')->nullable()->after('guarantor_id');
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['co_borrower_relationship', 'guarantor_relationship']);
        });
    }
};
