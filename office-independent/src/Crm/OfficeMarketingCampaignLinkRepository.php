<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/** CRM remains the source of truth; this repository only stores attribution. */
final class OfficeMarketingCampaignLinkRepository
{
    public function __construct(private PDO $db) {}

    public function createCampaign(string $code, string $name, ?string $sourceCode = null): int
    {
        $code = trim($code);
        $name = trim($name);
        $sourceCode = $sourceCode === null ? null : trim($sourceCode);
        if ($code === '' || strlen($code) > 64 || !preg_match('/^[a-zA-Z0-9_-]+$/D', $code)
            || $name === '' || mb_strlen($name) > 255
            || ($sourceCode !== null && strlen($sourceCode) > 64)) {
            throw new InvalidArgumentException('Ongeldige campagne.');
        }
        $stmt = $this->db->prepare('INSERT INTO office_marketing_campaigns (code,name,source_code) VALUES (?,?,?)');
        $stmt->execute([$code, $name, $sourceCode]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Attach an existing CRM lead OR opportunity to an existing campaign.
     * DB foreign keys enforce existence; duplicate links are rejected.
     * Callers must enforce authenticated operator permissions before invoking writes.
     */
    public function link(int $campaignId, string $targetType, int $targetId, ?string $sourceDetail = null): int
    {
        if ($campaignId < 1 || $targetId < 1 || !in_array($targetType, ['lead', 'opportunity'], true)
            || ($sourceDetail !== null && mb_strlen($sourceDetail) > 255)) {
            throw new InvalidArgumentException('Ongeldige campagnekoppeling.');
        }
        $leadId = $targetType === 'lead' ? $targetId : null;
        $opportunityId = $targetType === 'opportunity' ? $targetId : null;
        $stmt = $this->db->prepare('INSERT INTO office_marketing_crm_links (campaign_id,lead_id,opportunity_id,source_detail) VALUES (?,?,?,?)');
        $stmt->execute([$campaignId, $leadId, $opportunityId, $sourceDetail]);
        return (int)$this->db->lastInsertId();
    }

    /** Read only the attribution for a CRM-owned record; no duplicated CRM fields. */
    public function forCrmRecord(string $targetType, int $targetId): array
    {
        if ($targetId < 1 || !in_array($targetType, ['lead', 'opportunity'], true)) {
            throw new InvalidArgumentException('Ongeldig CRM-record.');
        }
        $column = $targetType === 'lead' ? 'lead_id' : 'opportunity_id';
        $stmt = $this->db->prepare(
            'SELECT l.id,l.campaign_id,c.code AS campaign_code,c.name AS campaign_name,c.source_code,l.source_detail,l.created_at ' .
            'FROM office_marketing_crm_links l JOIN office_marketing_campaigns c ON c.id=l.campaign_id ' .
            'WHERE l.' . $column . '=? ORDER BY l.id'
        );
        $stmt->execute([$targetId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Show CRM targets attached to one campaign, without copying personal data. */
    public function forCampaign(int $campaignId): array
    {
        if ($campaignId < 1) throw new InvalidArgumentException('Ongeldige campagne.');
        $stmt = $this->db->prepare(
            'SELECT id,campaign_id,lead_id,opportunity_id,source_detail,created_at ' .
            'FROM office_marketing_crm_links WHERE campaign_id=? ORDER BY id'
        );
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
