<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

/** Additive Office-owned CRM relation foundation; explicit installation only. */
final class OfficeCrmRelationsSchema {
    public static function statements(): array {
        return [
            "CREATE TABLE IF NOT EXISTS office_crm_relations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                relation_kind VARCHAR(16) NOT NULL,
                display_name VARCHAR(255) NOT NULL,
                legal_name VARCHAR(255) NULL,
                chamber_of_commerce VARCHAR(32) NULL,
                vat_number VARCHAR(48) NULL,
                email VARCHAR(254) NULL,
                invoice_email VARCHAR(254) NULL,
                offer_email VARCHAR(254) NULL,
                billing_street VARCHAR(255) NULL,
                billing_postal_code VARCHAR(32) NULL,
                billing_city VARCHAR(128) NULL,
                billing_country_code CHAR(2) NULL,
                phone VARCHAR(48) NULL,
                street VARCHAR(255) NULL,
                postal_code VARCHAR(32) NULL,
                city VARCHAR(128) NULL,
                country_code CHAR(2) NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'active',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_crm_relations_name (display_name),
                KEY idx_crm_relations_kvk (chamber_of_commerce)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_crm_relation_roles (
                relation_id BIGINT UNSIGNED NOT NULL,
                role_code VARCHAR(24) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (relation_id, role_code),
                CONSTRAINT fk_crm_role_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_crm_contact_people (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                given_name VARCHAR(128) NOT NULL,
                family_name VARCHAR(128) NOT NULL,
                email VARCHAR(254) NULL,
                phone VARCHAR(48) NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'active'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_crm_relation_contacts (
                relation_id BIGINT UNSIGNED NOT NULL,
                contact_id BIGINT UNSIGNED NOT NULL,
                job_title VARCHAR(128) NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (relation_id, contact_id),
                CONSTRAINT fk_crm_contact_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id),
                CONSTRAINT fk_crm_contact_person FOREIGN KEY (contact_id) REFERENCES office_crm_contact_people(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS office_crm_external_identities (
                provider_code VARCHAR(32) NOT NULL,
                external_id VARCHAR(128) NOT NULL,
                relation_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (provider_code, external_id),
                KEY idx_crm_external_relation (relation_id),
                CONSTRAINT fk_crm_external_relation FOREIGN KEY (relation_id) REFERENCES office_crm_relations(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];
    }
}

