<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use PDO;

/**
 * Authorized project-side offer application.
 * CRM-side relation authorization is not implemented yet; no generic create endpoint.
 */
final class OfficeOfferApplication
{
    private OfficeOfferRepository $offers;
    private OfficeOfferAccess $access;

    public function __construct(PDO $db)
    {
        $this->offers = new OfficeOfferRepository($db);
        $this->access = new OfficeOfferAccess($db);
    }

    public function createProjectDraft(int $operatorId, OfficeOfferDraft $draft): int
    {
        if ($draft->projectId === null) {
            throw new \InvalidArgumentException('Project draft requires a project');
        }
        if (!$this->access->canManageProject($operatorId, $draft->projectId)) {
            throw new \DomainException('Project management access required');
        }
        // Linking an existing change order also requires verifying its contract
        // belongs to the selected project. Until then fail closed.
        if ($draft->isProjectChange()) {
            throw new \DomainException('Change-order ownership verification not implemented');
        }
        return $this->offers->createDraft($draft);
    }

    public function listForProject(int $operatorId, int $projectId): array
    {
        if (!$this->access->canReadProject($operatorId, $projectId)) {
            throw new \DomainException('Project access required');
        }
        return $this->offers->forProject($projectId);
    }
}
