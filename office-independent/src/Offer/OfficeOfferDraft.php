<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

/**
 * Standalone commercial offer draft. No Drupal, project or calculation dependency.
 *
 * A project-linked change-order offer must name the change dossier explicitly.
 * A draft never changes contract value or payment terms.
 */
final class OfficeOfferDraft
{
    public function __construct(
        public readonly int $relationId,
        public readonly string $title,
        public readonly ?int $opportunityId = null,
        public readonly ?int $projectId = null,
        public readonly ?int $calculationId = null,
        public readonly ?string $calculationVersion = null,
        public readonly ?int $changeOrderId = null,
        public readonly ?string $changeType = null,
    ) {
        if ($relationId < 1 || trim($title) === '' || mb_strlen($title) > 255) {
            throw new \InvalidArgumentException('Invalid offer relation or title');
        }
        foreach ([$opportunityId, $projectId, $calculationId, $changeOrderId] as $id) {
            if ($id !== null && $id < 1) {
                throw new \InvalidArgumentException('Invalid offer reference');
            }
        }
        if (($calculationId === null) !== ($calculationVersion === null)) {
            throw new \InvalidArgumentException('Calculation and version must be supplied together');
        }
        if ($calculationVersion !== null && (trim($calculationVersion) === '' || strlen($calculationVersion) > 100)) {
            throw new \InvalidArgumentException('Invalid calculation version');
        }
        if ($changeOrderId !== null && $projectId === null) {
            throw new \InvalidArgumentException('Change-order offer requires a project');
        }
        if (($changeOrderId === null) !== ($changeType === null)) {
            throw new \InvalidArgumentException('Change-order type and reference must be supplied together');
        }
        if ($changeType !== null && !in_array($changeType, ['more', 'less'], true)) {
            throw new \InvalidArgumentException('Invalid change-order type');
        }
    }

    public function isProjectChange(): bool
    {
        return $this->changeOrderId !== null;
    }
}
