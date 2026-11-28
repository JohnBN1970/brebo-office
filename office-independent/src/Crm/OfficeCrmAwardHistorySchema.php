<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

/** Explicit migration only. No inferred historical award dates. */
final class OfficeCrmAwardHistorySchema {
    public static function statements(): array {
        return [
            "CREATE TABLE IF NOT EXISTS office_crm_award_events (
                opportunity_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                relation_id BIGINT UNSIGNED NOT NULL,
                relation_kind VARCHAR(16) NOT NULL,
                awarded_at DATETIME NOT NULL,
                actor_id BIGINT UNSIGNED NOT NULL,
                source VARCHAR(32) NOT NULL,
                KEY idx_crm_award_relation_date (relation_id, awarded_at),
                KEY idx_crm_award_date (awarded_at),
                FOREIGN KEY (opportunity_id) REFERENCES office_crm_opportunities(id),
                FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }
}
