<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;
use RuntimeException;

/**
 * Framework-independent persistence for website intake and its opportunity.
 *
 * Migration prerequisite: execute schema() on the Office database before use.
 * This is an opt-in component; it does not change the production Drupal route.
 */
final class WebsiteIntakeStore implements WebsiteLeadRepositoryInterface {

  public function __construct(private readonly PDO $db) {}

  public static function schema(): array {
    return [
      "CREATE TABLE IF NOT EXISTS office_website_intake (
        request_id CHAR(36) NOT NULL PRIMARY KEY,
        source VARCHAR(80) NOT NULL,
        payload_json LONGTEXT NOT NULL,
        status VARCHAR(32) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      )",
      "CREATE TABLE IF NOT EXISTS office_website_opportunity (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        request_id CHAR(36) NOT NULL UNIQUE,
        title VARCHAR(255) NOT NULL,
        stage VARCHAR(40) NOT NULL,
        lead_source VARCHAR(80) NOT NULL,
        acquisition_channel VARCHAR(80) NOT NULL,
        requirement_text TEXT NOT NULL,
        preliminary_scope_json LONGTEXT NOT NULL,
        requires_review TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_office_website_opportunity_intake
          FOREIGN KEY (request_id) REFERENCES office_website_intake(request_id)
      )",
    ];
  }

  /**
   * Atomic and idempotent. Returns the same opportunity for repeated requests.
   *
   * @param array<string, mixed> $payload
   * @return array{opportunity_id:int, duplicate:bool, state:string}
   */
  public function acceptWebsiteLead(string $requestId, string $source, array $payload): array {
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $requestId)) {
      throw new RuntimeException('Invalid website intake request ID.');
    }
    if ($source === '' || strlen($source) > 80) {
      throw new RuntimeException('Invalid website intake source.');
    }

    $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $this->db->beginTransaction();
    try {
      $insert = $this->db->prepare(
        "INSERT INTO office_website_intake (request_id, source, payload_json, status)
         VALUES (:id, :source, :payload, 'review_required')
         ON DUPLICATE KEY UPDATE request_id = request_id"
      );
      $insert->execute(['id' => $requestId, 'source' => $source, 'payload' => $encoded]);

      $identity = $this->db->prepare(
        'SELECT source, payload_json FROM office_website_intake WHERE request_id = :id FOR UPDATE'
      );
      $identity->execute(['id' => $requestId]);
      $stored = $identity->fetch(PDO::FETCH_ASSOC);
      if (!$stored || $stored['source'] !== $source || $stored['payload_json'] !== $encoded) {
        throw new RuntimeException('Conflicting payload for existing website intake request ID.');
      }

      $lookup = $this->db->prepare(
        'SELECT id FROM office_website_opportunity WHERE request_id = :id'
      );
      $lookup->execute(['id' => $requestId]);
      $existing = $lookup->fetchColumn();
      if ($existing !== false) {
        $this->db->commit();
        return ['opportunity_id' => (int) $existing, 'duplicate' => true, 'state' => 'review_required'];
      }

      $title = sprintf('Europakozijn - aanvraag [%s]', substr($requestId, 0, 8));
      $metadata = is_array($payload['selected'] ?? null) ? $payload['selected'] : [];
      $building = is_array($payload['observed']['building'] ?? null) ? $payload['observed']['building'] : [];
      $projectName = trim((string) ($metadata['project_name'] ?? $payload['observed']['project_name'] ?? ''));
      $address = trim((string) ($building['address'] ?? $building['formatted_address'] ?? ''));
      $label = $projectName !== '' ? $projectName : ($address !== '' ? $address : 'Nieuwe aanvraag');
      $requirement = mb_substr('Websiteproject: ' . $label, 0, 10000);
      $scope = is_array($payload['calculated']['preliminary_scope'] ?? null)
        ? $payload['calculated']['preliminary_scope']
        : [];
      $scopeJson = json_encode($scope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      $create = $this->db->prepare(
        "INSERT INTO office_website_opportunity
          (request_id, title, stage, lead_source, acquisition_channel, requirement_text, preliminary_scope_json, requires_review)
         VALUES (:id, :title, 'Lead', 'Website - Europakozijn', 'Portaal', :requirement, :scope, 1)"
      );
      $create->execute(['id' => $requestId, 'title' => $title, 'requirement' => $requirement, 'scope' => $scopeJson]);
      $opportunityId = (int) $this->db->lastInsertId();
      $this->db->commit();
      return ['opportunity_id' => $opportunityId, 'duplicate' => false, 'state' => 'review_required'];
    }
    catch (\Throwable $e) {
      if ($this->db->inTransaction()) {
        $this->db->rollBack();
      }
      throw $e;
    }
  }
}
