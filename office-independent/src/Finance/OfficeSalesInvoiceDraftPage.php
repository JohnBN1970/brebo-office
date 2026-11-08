<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use PDO;

/**
 * Office-native, read-only page handler.
 *
 * Router integration must supply the authenticated Office operator ID;
 * it must never accept an operator ID from request parameters.
 */
final class OfficeSalesInvoiceDraftPage
{
    public function __construct(
        private readonly PDO $officeDb,
        private readonly PDO $salesDb
    ) {
    }

    public function render(string $authenticatedOperatorId, ?int $officeProjectId = null): string
    {
        if ($authenticatedOperatorId === '') {
            throw new \DomainException('Office login vereist');
        }

        $drafts = (new OfficeSalesInvoiceDraftList(
            $this->officeDb,
            $this->salesDb
        ))->forOperator($authenticatedOperatorId, $officeProjectId);

        return OfficeSalesInvoiceDraftView::render($drafts);
    }
}
