<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;
use PDO;

final class OfficeCalcDealRevisionGuard
{
    public function __construct(private readonly PDO $db) {}

    public function assertReviewed(int $draftId): void
    {
        if (!$this->db->inTransaction() || $draftId < 1) {
            throw new DomainException('Deal revision verification requires a transaction');
        }
        $lock = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $q = $this->db->prepare('SELECT d.crm_revision, o.revision FROM office_calc_crm_drafts d JOIN office_crm_opportunities o ON o.id=d.opportunity_id WHERE d.id=?'.$lock);
        $q->execute([$draftId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || $row['crm_revision'] === null || $row['revision'] === null || (int)$row['crm_revision'] !== (int)$row['revision']) {
            throw new DomainException('Deal gewijzigd: controleer de wijzigingen voordat de calculatie wordt geaccepteerd.');
        }
    }
}
