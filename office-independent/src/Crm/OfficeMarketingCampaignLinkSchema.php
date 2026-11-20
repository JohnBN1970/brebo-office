<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

/**
 * Additive Marketing attribution only. CRM owns leads and opportunities.
 * Apply explicitly after the existing CRM lead/opportunity schema migrations.
 */
final class OfficeMarketingCampaignLinkSchema
{
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS office_marketing_campaigns (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                source_code VARCHAR(64) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_marketing_campaign_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_marketing_crm_links (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                campaign_id BIGINT UNSIGNED NOT NULL,
                lead_id BIGINT UNSIGNED NULL,
                opportunity_id BIGINT UNSIGNED NULL,
                source_detail VARCHAR(255) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_marketing_campaign_lead (campaign_id, lead_id),
                UNIQUE KEY uq_marketing_campaign_opportunity (campaign_id, opportunity_id),
                KEY idx_marketing_crm_lead (lead_id),
                KEY idx_marketing_crm_opportunity (opportunity_id),
                CONSTRAINT fk_marketing_link_campaign FOREIGN KEY (campaign_id) REFERENCES office_marketing_campaigns(id),
                CONSTRAINT fk_marketing_link_lead FOREIGN KEY (lead_id) REFERENCES office_crm_leads(id),
                CONSTRAINT fk_marketing_link_opportunity FOREIGN KEY (opportunity_id) REFERENCES office_crm_opportunities(id),
                CONSTRAINT chk_marketing_crm_link_target CHECK ((lead_id IS NOT NULL) <> (opportunity_id IS NOT NULL))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }
}
