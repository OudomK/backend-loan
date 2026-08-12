<?php

namespace Tests\Unit;

use App\Support\SearchResultRanker;
use PHPUnit\Framework\TestCase;

class SearchResultRankerTest extends TestCase
{
    public function test_it_ranks_exact_prefix_and_partial_matches_in_order(): void
    {
        $this->assertSame(0, SearchResultRanker::score('Sokha', ['Sokha']));
        $this->assertSame(1, SearchResultRanker::score('S', ['Sokha']));
        $this->assertSame(2, SearchResultRanker::score('kha', ['Sokha']));
        $this->assertSame(3, SearchResultRanker::score('other', ['Sokha']));
    }

    public function test_one_digit_matches_codes_and_ignores_formatting(): void
    {
        $this->assertSame(2, SearchResultRanker::score('3', ['QF-030']));
        $this->assertSame(1, SearchResultRanker::score('030', ['030 123 456']));
        $this->assertSame(0, SearchResultRanker::score('QF030', ['QF-030']));
    }

    public function test_it_checks_every_searchable_value(): void
    {
        $this->assertSame(0, SearchResultRanker::score('012345678', [
            'QF-030',
            'Sokha Client',
            '012 345 678',
        ]));
    }
}
