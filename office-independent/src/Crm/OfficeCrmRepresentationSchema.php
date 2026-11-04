<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;
use InvalidArgumentException;

/** Explicit relation links; no assumption that the manager is the debtor. */
final class OfficeCrmRepresentationSchema {
    public static function statements(): array {
        return [
            "CREATE TABLE IF NOT EXISTS office_crm_relation_representations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                principal_relation_id BIGINT UNSIGNED NOT NULL,
                representative_relation_id BIGINT UNSIGNED NOT NULL,
                representation_type VARCHAR(32) NOT NULL,
                valid_from DATE NULL,
                valid_until DATE NULL,
                notes TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_crm_principal (principal_relation_id),
                KEY idx_crm_representative (representative_relation_id),
                CONSTRAINT fk_crm_principal FOREIGN KEY (principal_relation_id) REFERENCES office_crm_relations(id),
                CONSTRAINT fk_crm_representative FOREIGN KEY (representative_relation_id) REFERENCES office_crm_relations(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_crm_relation_address_links (
                relation_id BIGINT UNSIGNED NOT NULL,
                address_purpose VARCHAR(24) NOT NULL,
                source_relation_id BIGINT UNSIGNED NOT NULL,
                care_of_label VARCHAR(255) NULL,
                valid_from DATE NULL,
                valid_until DATE NULL,
                PRIMARY KEY (relation_id, address_purpose, source_relation_id),
                CONSTRAINT fk_crm_address_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id),
                CONSTRAINT fk_crm_address_source FOREIGN KEY (source_relation_id) REFERENCES office_crm_relations(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_crm_assignment_parties (
                assignment_type VARCHAR(32) NOT NULL,
                assignment_id VARCHAR(128) NOT NULL,
                party_role VARCHAR(32) NOT NULL,
                relation_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (assignment_type, assignment_id, party_role),
                KEY idx_crm_assignment_party_relation (relation_id),
                CONSTRAINT fk_crm_assignment_party FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }

    public static function validateRepresentation(int $principalId, int $representativeId, string $type): void {
        if ($principalId < 1 || $representativeId < 1 || $principalId === $representativeId || $type !== 'property_management') {
            throw new InvalidArgumentException('Invalid property management representation');
        }
    }

    public static function validateAddressLink(int $relationId, int $sourceRelationId, string $purpose): void {
        if ($relationId < 1 || $sourceRelationId < 1 || $relationId === $sourceRelationId || $purpose !== 'correspondence') {
            throw new InvalidArgumentException('Invalid correspondence address link');
        }
    }

    public static function validateAssignmentRole(string $role): void {
        if (!in_array($role, ['beneficiary', 'contracting_party', 'invoice_debtor', 'ordering_party', 'communication_contact'], true)) {
            throw new InvalidArgumentException('Invalid assignment party role');
        }
    }
}
