<?php

namespace Tests\Feature;

use App\Models\Borrower;
use App\Models\SavingAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_borrower_crud_is_written_to_activity_log(): void
    {
        $borrower = Borrower::create([
            'customer_code' => 'BR-0001',
            'first_name' => 'Sok',
            'last_name' => 'Dara',
            'gender' => 'male',
            'phone' => '012345678',
            'status' => 'active',
        ]);

        $borrower->update([
            'phone' => '098765432',
        ]);

        $borrower->delete();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Borrower::class,
            'subject_id' => $borrower->id,
            'description' => 'created',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Borrower::class,
            'subject_id' => $borrower->id,
            'description' => 'updated',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Borrower::class,
            'subject_id' => $borrower->id,
            'description' => 'deleted',
        ]);
    }

    public function test_failed_login_attempt_is_logged(): void
    {
        $response = $this->postJson('/api/login', [
            'login' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'auth',
            'description' => 'Failed login attempt',
        ]);
    }

    public function test_saving_account_deposit_is_logged(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $account = SavingAccount::create([
            'account_number' => 'SA-000001',
            'account_type' => 'Voluntary',
            'currency' => 'USD',
            'balance' => 100,
            'status' => 'Active',
        ]);

        $response = $this->postJson("/api/saving-accounts/{$account->id}/deposit", [
            'amount' => 25,
            'reference_no' => 'DEP-001',
            'description' => 'Test deposit',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => SavingAccount::class,
            'subject_id' => $account->id,
            'log_name' => 'saving_accounts',
            'description' => 'Deposited into saving account',
        ]);

        $this->assertTrue(
            Activity::query()
                ->where('subject_type', SavingAccount::class)
                ->where('subject_id', $account->id)
                ->where('description', 'Deposited into saving account')
                ->exists()
        );
    }
}
