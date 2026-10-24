<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

/**
 * Office-native CRM schema proposal. Not run by any router or deploy hook.
 * Additive DDL only; empty start, no legacy Drupal data migration.
 */
final class OfficeCrmSchema {
  /** @return list<string> */
  public static function statements(): array {
    return [
      "CREATE TABLE IF NOT EXISTS office_crm_companies (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        registration_number VARCHAR(64) NULL,
        vat_number VARCHAR(64) NULL,
        email VARCHAR(255) NULL,
        phone VARCHAR(64) NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_crm_companies_name (name)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_crm_contacts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(128) NOT NULL,
        last_name VARCHAR(128) NOT NULL,
        email VARCHAR(255) NULL,
        phone VARCHAR(64) NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_crm_contacts_last_name (last_name)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_crm_company_contacts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        company_id BIGINT UNSIGNED NOT NULL,
        contact_id BIGINT UNSIGNED NOT NULL,
        role_label VARCHAR(128) NOT NULL DEFAULT '',
        department VARCHAR(128) NULL,
        is_primary TINYINT(1) NOT NULL DEFAULT 0,
        valid_from DATE NULL,
        valid_until DATE NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_crm_cc_company (company_id),
        INDEX idx_crm_cc_contact (contact_id),
        CONSTRAINT fk_crm_cc_company FOREIGN KEY (company_id) REFERENCES office_crm_companies(id),
        CONSTRAINT fk_crm_cc_contact FOREIGN KEY (contact_id) REFERENCES office_crm_contacts(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_crm_opportunities (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        stage VARCHAR(32) NOT NULL DEFAULT 'prospect',
        source VARCHAR(128) NULL,
        estimated_value DECIMAL(15,2) NULL,
        currency CHAR(3) NOT NULL DEFAULT 'EUR',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_crm_opportunity_companies (
        opportunity_id BIGINT UNSIGNED NOT NULL,
        company_id BIGINT UNSIGNED NOT NULL,
        role_label VARCHAR(128) NOT NULL DEFAULT '',
        PRIMARY KEY (opportunity_id, company_id, role_label),
        CONSTRAINT fk_crm_oc_opportunity FOREIGN KEY (opportunity_id) REFERENCES office_crm_opportunities(id),
        CONSTRAINT fk_crm_oc_company FOREIGN KEY (company_id) REFERENCES office_crm_companies(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_crm_opportunity_contacts (
        opportunity_id BIGINT UNSIGNED NOT NULL,
        contact_id BIGINT UNSIGNED NOT NULL,
        role_label VARCHAR(128) NOT NULL DEFAULT '',
        PRIMARY KEY (opportunity_id, contact_id, role_label),
        CONSTRAINT fk_crm_opc_opportunity FOREIGN KEY (opportunity_id) REFERENCES office_crm_opportunities(id),
        CONSTRAINT fk_crm_opc_contact FOREIGN KEY (contact_id) REFERENCES office_crm_contacts(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_crm_company_buildings (
        company_id BIGINT UNSIGNED NOT NULL,
        building_id BIGINT UNSIGNED NOT NULL,
        role_label VARCHAR(128) NOT NULL DEFAULT '',
        PRIMARY KEY (company_id, building_id, role_label),
        INDEX idx_crm_cb_building (building_id),
        CONSTRAINT fk_crm_cb_company FOREIGN KEY (company_id) REFERENCES office_crm_companies(id),
        CONSTRAINT fk_crm_cb_building FOREIGN KEY (building_id) REFERENCES office_buildings(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_crm_contact_buildings (
        contact_id BIGINT UNSIGNED NOT NULL,
        building_id BIGINT UNSIGNED NOT NULL,
        role_label VARCHAR(128) NOT NULL DEFAULT '',
        PRIMARY KEY (contact_id, building_id, role_label),
        INDEX idx_crm_ctb_building (building_id),
        CONSTRAINT fk_crm_ctb_contact FOREIGN KEY (contact_id) REFERENCES office_crm_contacts(id),
        CONSTRAINT fk_crm_ctb_building FOREIGN KEY (building_id) REFERENCES office_buildings(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_crm_company_projects (
        company_id BIGINT UNSIGNED NOT NULL,
        project_id BIGINT UNSIGNED NOT NULL,
        role_label VARCHAR(128) NOT NULL DEFAULT '',
        PRIMARY KEY (company_id, project_id, role_label),
        INDEX idx_crm_cp_project (project_id),
        CONSTRAINT fk_crm_cp_company FOREIGN KEY (company_id) REFERENCES office_crm_companies(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS office_crm_contact_projects (
        contact_id BIGINT UNSIGNED NOT NULL,
        project_id BIGINT UNSIGNED NOT NULL,
        role_label VARCHAR(128) NOT NULL DEFAULT '',
        PRIMARY KEY (contact_id, project_id, role_label),
        INDEX idx_crm_ctp_project (project_id),
        CONSTRAINT fk_crm_ctp_contact FOREIGN KEY (contact_id) REFERENCES office_crm_contacts(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
  }
}
