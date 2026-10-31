<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

/** Explicit installation only; never execute from a web request. */
final class OfficeOfferSchema
{
    public static function statements(): array
    {
        return [
            "CREATE TABLE office_offers (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                relation_id BIGINT UNSIGNED NOT NULL,
                opportunity_id BIGINT UNSIGNED NULL,
                office_project_id BIGINT UNSIGNED NULL,
                calculation_id BIGINT UNSIGNED NULL,
                calculation_version VARCHAR(100) NULL,
                change_order_id BIGINT UNSIGNED NULL,
                change_type VARCHAR(8) NULL,
                title VARCHAR(255) NOT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'draft',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_offers_relation (relation_id),
                KEY idx_offers_project (office_project_id),
                KEY idx_offers_change (change_order_id)
            )",
            "CREATE TABLE office_offer_versions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                offer_id BIGINT UNSIGNED NOT NULL,
                version_number INT UNSIGNED NOT NULL,
                content_json JSON NOT NULL,
                commercial_instalment_text TEXT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'draft',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_offer_version (offer_id, version_number),
                KEY idx_offer_version_offer (offer_id)
            )",
            "CREATE TABLE office_offer_acceptances (\n                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,\n                offer_id BIGINT UNSIGNED NOT NULL,\n                offer_version_id BIGINT UNSIGNED NOT NULL,\n                accepted_by VARCHAR(255) NOT NULL,\n                evidence_reference VARCHAR(500) NOT NULL,\n                accepted_at DATETIME NOT NULL,\n                recorded_by_operator_id BIGINT UNSIGNED NOT NULL,\n                recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,\n                UNIQUE KEY uq_offer_acceptance (offer_id),\n                UNIQUE KEY uq_offer_version_acceptance (offer_version_id)\n            )",
        ];
    }
}
