<?php

use PHPUnit\Framework\TestCase;

/** The periods of the export follow the clock of the person who asks (offset in seconds from UTC). */
final class RangeTest extends TestCase
{
    private function ts(string $s): int
    {
        return (int) strtotime($s . ' UTC');
    }

    public function testThisAndLastMonthInUtc(): void
    {
        $now = $this->ts('2026-11-15 12:00:00');
        $this->assertSame([$this->ts('2026-11-01 00:00:00'), PHP_INT_MAX], mod_ccenter_Period::bounds('m0', $now));
        $this->assertSame([$this->ts('2026-10-01 00:00:00'), $this->ts('2026-11-01 00:00:00')], mod_ccenter_Period::bounds('m1', $now));
    }

    public function testYears(): void
    {
        $now = $this->ts('2026-11-15 12:00:00');
        $this->assertSame([$this->ts('2026-01-01 00:00:00'), PHP_INT_MAX], mod_ccenter_Period::bounds('y0', $now));
        $this->assertSame([$this->ts('2025-01-01 00:00:00'), $this->ts('2026-01-01 00:00:00')], mod_ccenter_Period::bounds('y1', $now));
    }

    public function testJanuaryLooksBackToDecember(): void
    {
        $now = $this->ts('2026-01-10 08:00:00');
        $this->assertSame([$this->ts('2025-12-01 00:00:00'), $this->ts('2026-01-01 00:00:00')], mod_ccenter_Period::bounds('m1', $now));
    }

    public function testMonthsFollowTheClockOfTheVisitor(): void
    {
        // 2026-11-30 23:30 UTC is already December 1st at UTC+2: "this month" starts on December 1st, 00:00 at UTC+2
        $now = $this->ts('2026-11-30 23:30:00');
        [$from] = mod_ccenter_Period::bounds('m0', $now, 7200);
        $this->assertSame($this->ts('2026-12-01 00:00:00') - 7200, $from);
        // and in UTC it is still November
        [$utcFrom] = mod_ccenter_Period::bounds('m0', $now, 0);
        $this->assertSame($this->ts('2026-11-01 00:00:00'), $utcFrom);
    }

    public function testAllAndUnknownRangesHaveNoLimits(): void
    {
        $this->assertSame([null, null], mod_ccenter_Period::bounds('all', 1));
        $this->assertSame([null, null], mod_ccenter_Period::bounds('', 1));
        $this->assertSame([null, null], mod_ccenter_Period::bounds("m0'; DROP", 1));
    }
}
