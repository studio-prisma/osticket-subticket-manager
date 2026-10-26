<?php

declare(strict_types=1);

namespace SubticketManager\UI;

use SubticketManager\Security\TicketAccessGuard;

/**
 * OverviewFilter - decides what the hierarchy overview (scp/subtickets.php)
 * may show to the current agent
 *
 * Same rule as the ticket-view panel: a child the agent cannot view is left
 * out, a parent the agent cannot view is redacted (no number, no subject, no
 * link). A parent whose children are all hidden does not appear at all.
 *
 * @package SubticketManager
 */
final class OverviewFilter
{
    private TicketAccessGuard $accessGuard;

    public function __construct(TicketAccessGuard $accessGuard)
    {
        $this->accessGuard = $accessGuard;
    }

    /**
     * Group visible child rows by parent id.
     *
     * @param array<int, array<string, mixed>> $rows Child rows with ticket_id and ticket_pid
     * @return array<int, array<int, array<string, mixed>>> parent id => visible child rows
     */
    public function visibleChildren(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $childId = (int)($row['ticket_id'] ?? 0);
            $parentId = (int)($row['ticket_pid'] ?? 0);

            if ($parentId < 1 || !$this->accessGuard->canView($childId)) {
                continue;
            }

            $grouped[$parentId][] = $row;
        }

        return $grouped;
    }

    /**
     * May the parent's number, subject and status be shown?
     */
    public function showParent(int $parentId): bool
    {
        return $this->accessGuard->canView($parentId);
    }
}
