<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('marital_status')->nullable()->after('gender');
        });

        Schema::table('co_borrowers', function (Blueprint $table) {
            $table->string('marital_status')->nullable()->after('gender');
        });

        Schema::table('guarantors', function (Blueprint $table) {
            $table->string('marital_status')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropColumn('marital_status');
        });

        Schema::table('co_borrowers', function (Blueprint $table) {
            $table->dropColumn('marital_status');
        });

        Schema::table('guarantors', function (Blueprint $table) {
            $table->dropColumn('marital_status');
        });
    }
};
