<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $user = User::firstOrNew(['email' => 'test@example.com']);
        $user->name = 'Admin User';
        $user->password = Hash::make('password');
        $user->role = 'admin';
        $user->save();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't delete users on rollback to be safe
    }
};
