<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('customer_code')->unique()->nullable()->after('id');
        });

        Schema::table('co_borrowers', function (Blueprint $table) {
            $table->string('customer_code')->unique()->nullable()->after('id');
        });

        Schema::table('guarantors', function (Blueprint $table) {
            $table->string('customer_code')->unique()->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropColumn('customer_code');
        });

        Schema::table('co_borrowers', function (Blueprint $table) {
            $table->dropColumn('customer_code');
        });

        Schema::table('guarantors', function (Blueprint $table) {
            $table->dropColumn('customer_code');
        });
    }
};
