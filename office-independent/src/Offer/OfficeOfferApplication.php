<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

use PDO;
use Brebo\Office\Project\OfficeProjectChangeOrderRepository;
use Brebo\Office\Project\OfficeProjectContractRepository;

/**
 * Authorized project-side offer application.
 * CRM-side relation authorization is not implemented yet; no generic create endpoint.
 */
final class OfficeOfferApplication
{
    private OfficeOfferRepository $offers;
    private OfficeOfferAccess $access;
    private OfficeOfferModulePermission $modulePermissions;
    private OfficeProjectContractRepository $contracts;
    private OfficeProjectChangeOrderRepository $changes;

    public function __construct(PDO $db)
    {
        $this->offers = new OfficeOfferRepository($db);
        $this->access = new OfficeOfferAccess($db);
        $this->modulePermissions = new OfficeOfferModulePermission($db);
        $this->contracts = new OfficeProjectContractRepository($db);
        $this->changes = new OfficeProjectChangeOrderRepository($db);
    }

    public function createProjectDraft(int|string $operatorId, OfficeOfferDraft $draft): int
    {
        if ($draft->projectId === null) {
            throw new \InvalidArgumentException('Project draft requires a project');
        }
        if (!$this->modulePermissions->allows($operatorId, 'create')
            || !$this->access->canManageProject($operatorId, $draft->projectId)) {
            throw new \DomainException('Project management access required');
        }
        if ($draft->isProjectChange()) {
            $this->requireMatchingChangeOrder($draft);
        }
        return $this->offers->createDraft($draft);
    }

    private function requireMatchingChangeOrder(OfficeOfferDraft $draft): void
    {
        $projectId = $draft->projectId;
        $changeOrderId = $draft->changeOrderId;
        if ($projectId === null || $changeOrderId === null) {
            throw new \DomainException('Change-order context required');
        }
        foreach ($this->contracts->forProject($projectId) as $contract) {
            foreach ($this->changes->forContract((int) $contract['id']) as $change) {
                if ((int) $change['id'] !== $changeOrderId) {
                    continue;
                }
                if ($change['change_type'] !== $draft->changeType) {
                    throw new \DomainException('Change-order type mismatch');
                }
                if ($change['status'] !== 'draft') {
                    throw new \DomainException('Change-order must be a draft');
                }
                return;
            }
        }
        throw new \DomainException('Change-order does not belong to project');
    }

    public function listForProject(int|string $operatorId, int $projectId): array
    {
        if (!$this->modulePermissions->allows($operatorId, 'read')
            || !$this->access->canReadProject($operatorId, $projectId)) {
            throw new \DomainException('Project access required');
        }
        return $this->offers->forProject($projectId);
    }
}
