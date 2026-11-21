<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

/**
 * Review-only schema proposal. Never executed automatically.
 * Existing office_buildings records are unchanged.
 * Apply only after isolated DB backup, restore rehearsal and explicit GO.
 */
final class OfficeResidentAddressSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_building_addresses (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        building_id BIGINT UNSIGNED NOT NULL,
        bag_nummeraanduiding_id CHAR(16) NULL,
        street VARCHAR(255) NOT NULL,
        house_number VARCHAR(16) NOT NULL,
        addition VARCHAR(32) NOT NULL DEFAULT '',
        postal_code VARCHAR(16) NOT NULL,
        city VARCHAR(160) NULL,
        normalized_address_key VARCHAR(500) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_building_address (building_id, normalized_address_key),
        KEY idx_address_postal (postal_code, house_number),
        KEY idx_address_bag (bag_nummeraanduiding_id),
        CONSTRAINT fk_office_building_address_building FOREIGN KEY (building_id)
          REFERENCES office_buildings(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE office_address_residents (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        address_id BIGINT UNSIGNED NOT NULL,
        resident_name VARCHAR(160) NULL,
        phone VARCHAR(40) NULL,
        email VARCHAR(254) NULL,
        occupancy_status VARCHAR(32) NOT NULL DEFAULT 'unknown',
        valid_from DATE NULL,
        valid_until DATE NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_resident_address (address_id),
        CONSTRAINT fk_office_resident_address FOREIGN KEY (address_id)
          REFERENCES office_building_addresses(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE office_address_intake_proposals (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        source_type VARCHAR(32) NOT NULL,
        source_id VARCHAR(255) NOT NULL,
        source_row VARCHAR(64) NOT NULL,
        proposed_address_json JSON NOT NULL,
        proposed_resident_json JSON NULL,
        candidate_building_id BIGINT UNSIGNED NULL,
        candidate_address_id BIGINT UNSIGNED NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'needs_review',
        reviewed_by VARCHAR(160) NULL,
        reviewed_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_address_proposal_source (source_type, source_id, source_row),
        KEY idx_address_proposal_status (status, created_at),
        KEY idx_address_proposal_building (candidate_building_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE office_address_resident_audit (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        entity_type VARCHAR(32) NOT NULL,
        entity_id BIGINT UNSIGNED NOT NULL,
        action VARCHAR(32) NOT NULL,
        actor_id VARCHAR(160) NOT NULL,
        source_type VARCHAR(32) NULL,
        source_id VARCHAR(255) NULL,
        occurred_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_address_audit_entity (entity_type, entity_id, occurred_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }
}
