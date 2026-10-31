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
    private OfficeOfferModulePermission $modulePermissions;
    private OfficeOfferChangeLinkRepository $links;

    public function __construct(private readonly PDO $db)
    {
        $this->access = new OfficeOfferAccess($db);
        $this->modulePermissions = new OfficeOfferModulePermission($db);
        $this->links = new OfficeOfferChangeLinkRepository($db);
    }

    public function linkAcceptedChange(int|string $operatorId, int $acceptanceId): int
    {
        if ((is_int($operatorId) ? $operatorId < 1 : trim($operatorId) === '') || $acceptanceId < 1) {
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
        if (!$this->modulePermissions->allows($operatorId, 'link_change')
            || $projectId === false || $projectId === null
            || !$this->access->canManageProject($operatorId, (int) $projectId)) {
            throw new \DomainException('Project management access required');
        }
        // Repository revalidates project and contract ownership under row locks.
        return $this->links->link($acceptanceId, $operatorId);
    }
}
