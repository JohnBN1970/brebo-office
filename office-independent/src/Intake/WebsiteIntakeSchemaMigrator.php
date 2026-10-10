<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;
use RuntimeException;

/**
 * Idempotent, additive migrations for the independent intake schema.
 *
 * Run before serving traffic; do not execute DDL inside intake transactions.
 */
final class WebsiteIntakeSchemaMigrator {

  public function __construct(private readonly PDO $db) {}

  public function migrate(): void {
    foreach (WebsiteIntakeStore::schema() as $statement) {
      $this->db->exec($statement);
    }
    $columns = $this->db->query('SHOW COLUMNS FROM office_website_opportunity')->fetchAll(PDO::FETCH_COLUMN);
    $additions = [
      'probability' => 'TINYINT UNSIGNED NOT NULL DEFAULT 10',
      'active' => 'TINYINT(1) NOT NULL DEFAULT 1',
      'next_action' => "VARCHAR(255) NOT NULL DEFAULT 'Projectstukken en automatisch herkende gegevens beoordelen.'",
      'lead_source' => "VARCHAR(80) NOT NULL DEFAULT 'Website - Europakozijn'",
      'acquisition_channel' => "VARCHAR(80) NOT NULL DEFAULT 'Portaal'",
      'requirement_text' => "TEXT NULL",
      'preliminary_scope_json' => "LONGTEXT NULL",
    ];
    foreach ($additions as $name => $definition) {
      if (!in_array($name, $columns, true)) {
        $this->db->exec("ALTER TABLE office_website_opportunity ADD COLUMN `$name` $definition");
      }
    }
    $this->db->exec("UPDATE office_website_opportunity SET requirement_text = '' WHERE requirement_text IS NULL");
    $this->db->exec("UPDATE office_website_opportunity SET preliminary_scope_json = '{}' WHERE preliminary_scope_json IS NULL");
    foreach (['requirement_text' => 'TEXT', 'preliminary_scope_json' => 'LONGTEXT'] as $name => $type) {
      $this->db->exec("ALTER TABLE office_website_opportunity MODIFY COLUMN `$name` $type NOT NULL");
    }
    $required = ['request_id', 'title', 'stage', 'probability', 'active', 'next_action', 'lead_source', 'acquisition_channel', 'requirement_text', 'preliminary_scope_json', 'requires_review'];
    $actual = $this->db->query('SHOW COLUMNS FROM office_website_opportunity')->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff($required, $actual) !== []) {
      throw new RuntimeException('Independent intake schema migration incomplete.');
    }
  }
}
