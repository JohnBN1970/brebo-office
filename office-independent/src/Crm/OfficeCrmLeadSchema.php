<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

/**
 * Additive lead intake schema. Installation is an explicit migration step.
 * Lead source is immutable in the original intake record; transitions are audited.
 */
final class OfficeCrmLeadSchema
{
    public static function statements(): array
    {
        return [
            "CREATE TABLE IF NOT EXISTS office_crm_lead_sources (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(64) NOT NULL,
                label VARCHAR(120) NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_crm_lead_source_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_crm_leads (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                source_id BIGINT UNSIGNED NOT NULL,
                source_detail VARCHAR(255) NULL,
                campaign VARCHAR(255) NULL,
                external_reference VARCHAR(255) NULL,
                title VARCHAR(255) NOT NULL,
                contact_name VARCHAR(255) NULL,
                contact_email VARCHAR(254) NULL,
                relation_id BIGINT UNSIGNED NULL,
                building_id BIGINT UNSIGNED NULL,
                owner_operator_id VARCHAR(128) NULL,
                description TEXT NULL,
                stage VARCHAR(32) NOT NULL DEFAULT 'new',
                status VARCHAR(24) NOT NULL DEFAULT 'active',
                converted_opportunity_id BIGINT UNSIGNED NULL,
                converted_at DATETIME NULL,
                received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_crm_leads_board (status, stage, received_at),
                KEY idx_crm_leads_source (source_id),
                KEY idx_crm_leads_relation (relation_id),
                UNIQUE KEY uq_crm_leads_converted_opportunity (converted_opportunity_id),
                CONSTRAINT fk_crm_leads_source FOREIGN KEY (source_id) REFERENCES office_crm_lead_sources(id),
                CONSTRAINT fk_crm_leads_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id),
                CONSTRAINT fk_crm_leads_opportunity FOREIGN KEY (converted_opportunity_id) REFERENCES office_crm_opportunities(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_crm_lead_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                lead_id BIGINT UNSIGNED NOT NULL,
                actor_operator_id VARCHAR(128) NULL,
                event_type VARCHAR(48) NOT NULL,
                old_stage VARCHAR(32) NULL,
                new_stage VARCHAR(32) NULL,
                note TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_crm_lead_events_lead (lead_id, created_at),
                CONSTRAINT fk_crm_lead_events_lead FOREIGN KEY (lead_id) REFERENCES office_crm_leads(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }

    public static function seedStatements(): array
    {
        $statements = [];
        foreach (self::sourceCodes() as $code => $label) {
            $codeSql = str_replace("'", "''", $code);
            $labelSql = str_replace("'", "''", $label);
            $statements[] = "INSERT INTO office_crm_lead_sources (code,label,active) VALUES ('".$codeSql."','".$labelSql."',1) ON DUPLICATE KEY UPDATE code=code";
        }
        return $statements;
    }

    public static function sourceCodes(): array
    {
        return [
            'trade_fair' => 'Beurs',
            'cold_network' => 'Koud netwerk',
            'warm_network' => 'Warm netwerk',
            'website' => 'Website',
            'platform' => 'Platform',
            'google_ads' => 'Google Ads',
            'social_media' => 'Social media',
            'referral' => 'Doorverwijzing',
            'existing_customer' => 'Bestaande klant',
            'other' => 'Overig',
        ];
    }
}
