<?php

namespace Tests\Unit;

use App\Services\Operations\OperationalPeriod;
use PHPUnit\Framework\TestCase;

class OperationalPeriodTest extends TestCase
{
    public function test_default_start_date_matches_the_brewery_cutover_date(): void
    {
        $this->assertSame('2026-07-01', OperationalPeriod::DEFAULT_START_DATE);
    }
}
