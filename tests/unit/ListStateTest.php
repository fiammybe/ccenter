<?php

use PHPUnit\Framework\TestCase;

final class ListStateTest extends TestCase
{
    private const COMBO = "All: - a b c\nOpen: - a";

    private function state(array $request, array &$session, array $init = [])
    {
        $init += ['mtime' => 'ASC', 'status' => 'ASC', 'orders' => ['mtime']];
        return new mod_ccenter_ListState('t', $init, self::COMBO, $session, $request);
    }

    public function testStartsWithTheFirstCombinationAndRemembers(): void
    {
        $session = [];
        $s = $this->state([], $session);
        $this->assertSame('- a b c', $s->statuses());
        $s2 = $this->state(['stat' => '- a'], $session);
        $this->assertSame('- a', $s2->statuses());
        $this->assertSame('- a', $this->state([], $session)->statuses(), 'kept in the session');
        $this->assertSame('- a b c', $this->state(['reset' => 'yes'], $session)->statuses());
    }

    public function testStatusInputIsCleaned(): void
    {
        $session = [];
        $this->assertMatchesRegularExpression('/^[a-dx\- ]*$/', $this->state(['stat' => "- a'; DROP TABLE x;--"], $session)->statuses());
    }

    public function testSortChangeMakesTheColumnFirst(): void
    {
        $session = [];
        $s = $this->state(['status' => 'desc'], $session);
        $this->assertSame([['status', 'DESC'], ['mtime', 'ASC']], $s->orders());
        $labels = $s->labels(['mtime' => 'Date', 'status' => 'Status']);
        $this->assertSame('asc', $labels[1]['next']);
        $this->assertSame('asc', $labels[0]['value']);
    }

    public function testArraysAndUnknownKeysAreIgnored(): void
    {
        $session = [];
        $s = $this->state(['orders' => ['x'], 'status' => ['a'], 'nothing' => 'desc'], $session);
        $this->assertSame([['mtime', 'ASC']], $s->orders());
    }

    public function testBreadcrumbsLastEntryHasNoLinkAndNamesAreEscaped(): void
    {
        $b = new mod_ccenter_Breadcrumbs('Contact', 'http://x/modules/ccenter');
        $b->add('<b>Re</b> "q"', 'reception.php')->add('End', '');
        $trail = $b->get();
        $this->assertSame('http://x/modules/ccenter/', $trail[0]['url']);
        $this->assertSame('http://x/modules/ccenter/reception.php', $trail[1]['url']);
        $this->assertSame('Re &quot;q&quot;', $trail[1]['name']);
        $this->assertSame('', $trail[2]['url']);
    }
}
