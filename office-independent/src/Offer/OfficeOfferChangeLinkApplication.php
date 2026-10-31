<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use PDO;

/**
 * Application boundary for accepted-offer to project-change linkage.
 * This does not approve change orders or update financial terms.
 */
final class OfficeOfferChangeLinkApplication
{
    private OfficeOfferAccess $access;
    private OfficeOfferChangeLinkRepository $links;

    public function __construct(private readonly PDO $db)
    {
        $this->access = new OfficeOfferAccess($db);
        $this->links = new OfficeOfferChangeLinkRepository($db);
    }

    public function linkAcceptedChange(int $operatorId, int $acceptanceId): int
    {
        if ($operatorId < 1 || $acceptanceId < 1) {
            throw new \InvalidArgumentException('Invalid operator or acceptance');
        }
        $query = $this->db->prepare(
            'SELECT o.office_project_id
             FROM office_offer_acceptances a
             JOIN office_offers o ON o.id = a.offer_id
             WHERE a.id = :acceptance_id'
        );
        $query->execute([':acceptance_id' => $acceptanceId]);
        $projectId = $query->fetchColumn();
        if ($projectId === false || $projectId === null
            || !$this->access->canManageProject($operatorId, (int) $projectId)) {
            throw new \DomainException('Project management access required');
        }
        // Repository revalidates project and contract ownership under row locks.
        return $this->links->link($acceptanceId, $operatorId);
    }
}
