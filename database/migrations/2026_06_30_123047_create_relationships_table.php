<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('relationships', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default relationships
        $defaults = ['Spouse', 'Husband', 'Wife', 'Sibling', 'Parent', 'Child', 'Relative', 'Partner', 'Others'];
        $now = now();
        $insertData = array_map(function ($name) use ($now) {
            return ['name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
        }, $defaults);
        
        DB::table('relationships')->insert($insertData);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};
