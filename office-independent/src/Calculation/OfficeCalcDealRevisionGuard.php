<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;
use PDO;

final class OfficeCalcDealRevisionGuard
{
    public function __construct(private readonly PDO $db) {}

    public function review(int $draftId, int $actorId, int $expectedRevision, callable $rights): void
    {
        if ($this->db->inTransaction() || $expectedRevision < 1) {
            throw new DomainException('Invalid Deal review transaction');
        }
        require_once __DIR__.'/OfficeCalcCrmDraftAccess.php';
        $this->db->beginTransaction();
        try {
            $lock = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $q = $this->db->prepare('SELECT o.revision FROM office_calc_crm_drafts d JOIN office_crm_opportunities o ON o.id=d.opportunity_id WHERE d.id=?'.$lock);
            $q->execute([$draftId]);
            $revision = $q->fetchColumn();
            (new OfficeCalcCrmDraftAccess($this->db))->assertCanManage($actorId,$draftId,$rights);
            if ($revision === false || $revision === null || (int)$revision !== $expectedRevision) {
                throw new DomainException('Deal opnieuw gewijzigd; heropen de calculatie en controleer de actuele revisie.');
            }
            // A repeated acknowledgment must not create duplicate audit decisions.
            $prior = $this->db->prepare('SELECT 1 FROM office_calc_deal_revision_reviews WHERE draft_id=? AND deal_revision=? LIMIT 1');
            $prior->execute([$draftId,$expectedRevision]);
            if ($prior->fetchColumn()) {
                $this->db->commit();
                return;
            }
            $insert = $this->db->prepare('INSERT INTO office_calc_deal_revision_reviews (draft_id,deal_revision,reviewed_by) VALUES (?,?,?)');
            $insert->execute([$draftId,$expectedRevision,$actorId]);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function assertReviewed(int $draftId): void
    {
        if (!$this->db->inTransaction() || $draftId < 1) {
            throw new DomainException('Deal revision verification requires a transaction');
        }
        $lock = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $q = $this->db->prepare('SELECT d.crm_revision, o.revision FROM office_calc_crm_drafts d JOIN office_crm_opportunities o ON o.id=d.opportunity_id WHERE d.id=?'.$lock);
        $q->execute([$draftId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || $row['crm_revision'] === null || $row['revision'] === null) {
            throw new DomainException('Deal gewijzigd: controleer de wijzigingen voordat de calculatie wordt geaccepteerd.');
        }
        if ((int)$row['crm_revision'] === (int)$row['revision']) return;
        $review = $this->db->prepare('SELECT 1 FROM office_calc_deal_revision_reviews WHERE draft_id=? AND deal_revision=?');
        $review->execute([$draftId,(int)$row['revision']]);
        if (!$review->fetchColumn()) throw new DomainException('Deal gewijzigd: controleer de wijzigingen voordat de calculatie wordt geaccepteerd.');
    }
}
