<?php

/**
 * Periods used by the export of messages.
 */
class mod_ccenter_Period
{
    /**
     * Start (included) and end (excluded) of a period.
     *
     * @param string $range  m0 this month, m1 last month, y0 this year, y1 last year, anything else: all
     * @param int    $now    timestamp
     * @param int    $offset seconds the visitor's clock is ahead of UTC (months and years follow the visitor's clock)
     * @return array [from, to] as timestamps, both null for "all"
     */
    public static function bounds($range, $now, $offset = 0)
    {
        $local = $now + $offset;
        $y = (int) gmdate('Y', $local);
        $m = (int) gmdate('n', $local);
        $start = function ($month, $year) use ($offset) {
            return gmmktime(0, 0, 0, $month, 1, $year) - $offset;
        };
        switch ($range) {
            case 'm0':
                return [$start($m, $y), PHP_INT_MAX];
            case 'm1':
                return [$start($m - 1, $y), $start($m, $y)];
            case 'y0':
                return [$start(1, $y), PHP_INT_MAX];
            case 'y1':
                return [$start(1, $y - 1), $start(1, $y)];
        }
        return [null, null];
    }
}
