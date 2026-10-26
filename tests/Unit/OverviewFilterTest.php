<?php
/**
 * Regression tests for the hierarchy overview (scp/subtickets.php)
 *
 * The page listed every hierarchy with number and subject across all
 * departments; only isStaff() was checked. OverviewFilter applies the same
 * rule as the ticket-view panel.
 */

namespace SubticketManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SubticketManager\Security\TicketAccessGuard;
use SubticketManager\UI\OverviewFilter;

require_once dirname(__DIR__) . '/bootstrap.php';

class OverviewFilterTest extends TestCase
{
    private function filterFor(array $visibleIds): OverviewFilter
    {
        return new OverviewFilter(new TicketAccessGuard(
            function (int $ticketId, string $perm) use ($visibleIds): bool {
                return $perm === 'view' && in_array($ticketId, $visibleIds, true);
            }
        ));
    }

    private function rows(): array
    {
        return [
            ['ticket_id' => 11, 'ticket_pid' => 1, 'number' => '100011'],
            ['ticket_id' => 12, 'ticket_pid' => 1, 'number' => '100012'],
            ['ticket_id' => 21, 'ticket_pid' => 2, 'number' => '100021'],
        ];
    }

    public function testChildrenTheAgentCannotViewAreLeftOut(): void
    {
        $grouped = $this->filterFor([1, 11, 2])->visibleChildren($this->rows());

        $this->assertSame([1], array_keys($grouped));
        $this->assertCount(1, $grouped[1]);
        $this->assertSame(11, $grouped[1][0]['ticket_id']);
    }

    public function testParentWithOnlyHiddenChildrenDoesNotAppear(): void
    {
        $grouped = $this->filterFor([2])->visibleChildren($this->rows());

        $this->assertSame([], $grouped);
    }

    public function testParentTheAgentCannotViewIsRedacted(): void
    {
        $filter = $this->filterFor([11, 12]);

        $this->assertSame([1], array_keys($filter->visibleChildren($this->rows())));
        $this->assertFalse($filter->showParent(1));
    }

    public function testFullAccessKeepsEverything(): void
    {
        $filter = $this->filterFor([1, 2, 11, 12, 21]);
        $grouped = $filter->visibleChildren($this->rows());

        $this->assertCount(2, $grouped[1]);
        $this->assertCount(1, $grouped[2]);
        $this->assertTrue($filter->showParent(1));
    }

    public function testRowsWithoutParentAreIgnored(): void
    {
        $rows = [['ticket_id' => 5, 'ticket_pid' => 0], ['ticket_id' => 6]];

        $this->assertSame([], $this->filterFor([5, 6])->visibleChildren($rows));
    }
}
