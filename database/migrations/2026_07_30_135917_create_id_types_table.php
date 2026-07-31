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
        Schema::create('id_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed initial data
        $initialTypes = [
            'National ID',
            'Passport',
            'Family Book',
            'Birth Certificate',
            'Driving License',
        ];

        foreach ($initialTypes as $type) {
            \Illuminate\Support\Facades\DB::table('id_types')->insert([
                'name' => $type,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_types');
    }
};
