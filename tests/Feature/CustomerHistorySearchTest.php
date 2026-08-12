<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerHistoryController;
use App\Http\Controllers\BorrowerController;
use App\Models\Borrower;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerHistorySearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Borrower::unsetEventDispatcher();
        activity()->disableLogging();
        Schema::dropAllTables();
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('row_no')->nullable();
            $table->string('customer_code')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('latin_name')->nullable();
            $table->string('nickname')->nullable();
            $table->string('gender')->nullable();
            $table->string('phone')->nullable();
            $table->string('id_number')->nullable();
            $table->string('village')->nullable();
            $table->string('commune')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('customer_type')->default('Borrower');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->nullable();
            $table->string('loan_code')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function test_short_numeric_query_suggests_code_matches_without_using_id_number(): void
    {
        $this->borrower('QF-001', 1, 'First', 'Client', '030123456');
        $this->borrower('QF-030', 30, 'Target', 'Client', '999999999');
        $this->borrower('QF-031', 31, 'Another', 'Client', '030987654');

        $results = $this->search('030');

        $this->assertCount(1, $results);
        $this->assertSame('QF-030', $results[0]['code']);
        $this->assertSame('Customer code', $results[0]['matched_on']);
    }

    public function test_numeric_suggestions_are_returned_from_the_first_digit(): void
    {
        $this->borrower('QF-001', 1, 'First', 'Client', '111111111');
        $this->borrower('QF-010', 10, 'Tenth', 'Client', '222222222');
        $this->borrower('QF-030', 30, 'Thirtieth', 'Client', '333333333');

        foreach (['0', '03', '030'] as $query) {
            $results = $this->search($query);

            $this->assertNotEmpty($results, "Expected a suggestion for {$query}");
        }

        $results = $this->search('030');
        $this->assertSame('QF-030', $results[0]['code']);
    }

    public function test_exact_customer_code_is_ranked_before_partial_matches(): void
    {
        $this->borrower('QF-100', 100, 'QF-030 fan', 'Client', '123456789');
        $this->borrower('QF-030', 30, 'Target', 'Client', '987654321');

        $results = $this->search('QF-030');

        $this->assertSame('QF-030', $results[0]['code']);
    }

    public function test_phone_and_id_search_require_at_least_four_characters(): void
    {
        $this->borrower('QF-001', 1, 'Phone', 'Match', '987654321', '012 345 678');

        $this->assertSame([], $this->search('345'));

        $results = $this->search('3456');
        $this->assertCount(1, $results);
        $this->assertSame('Phone', $results[0]['matched_on']);
    }

    public function test_name_suggestions_are_returned_from_the_first_character(): void
    {
        $this->borrower('QF-001', 1, 'Sokha', 'Client', '111111111');

        foreach (['S', 'So', 'Sok'] as $query) {
            $results = $this->search($query);

            $this->assertCount(1, $results, "Expected a suggestion for {$query}");
            $this->assertSame('Sokha Client', $results[0]['name']);
        }
    }

    public function test_loan_application_suggests_from_one_letter_or_digit(): void
    {
        $this->borrower('QF-030', 30, 'Sokha', 'Client', '111111111');

        foreach (['S', '0'] as $query) {
            $request = Request::create('/api/borrowers', 'GET', ['search' => $query]);
            $results = app(BorrowerController::class)->index($request)->getData(true);

            $this->assertNotEmpty($results, "Expected a Loan Application suggestion for {$query}");
            $this->assertSame('QF-030', $results[0]['code']);
        }
    }

    private function search(string $query): array
    {
        $request = Request::create('/api/customer-history/search', 'GET', ['query' => $query]);
        $response = app(CustomerHistoryController::class)->search($request);

        return $response->getData(true);
    }

    private function borrower(
        string $code,
        int $rowNo,
        string $firstName,
        string $lastName,
        string $idNumber,
        string $phone = ''
    ): Borrower {
        return Borrower::query()->create([
            'row_no' => $rowNo,
            'customer_code' => $code,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'id_number' => $idNumber,
            'customer_type' => 'Borrower',
        ]);
    }
}
