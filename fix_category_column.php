<?php
// fix_category_column.php - Run once: php fix_category_column.php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if (!Schema::hasColumn('roles', 'category')) {
    Schema::table('roles', function (Blueprint $table) {
        $table->string('category', 20)->default('admin')->after('guard_name');
    });
    echo "✅ Category column added to roles table.\n";
} else {
    echo "✅ Category column already exists — nothing to do.\n";
}
