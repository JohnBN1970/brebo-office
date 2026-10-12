<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/** Read-only CRM list for newly received website leads, without Drupal. */
final class WebsiteWebsiteOpportunityReadRepository {

  public function __construct(private readonly PDO $db) {}

  /** @return list<array<string,mixed>> */
  public function recent(int $limit = 50): array {
    if ($limit < 1 || $limit > 100) {
      throw new \InvalidArgumentException('CRM list limit must be between 1 and 100.');
    }
    $statement = $this->db->prepare(
      'SELECT o.id, o.title, o.stage, o.next_action, o.requires_review, o.created_at, i.source, i.request_id
       FROM office_website_opportunity o
       INNER JOIN office_website_intake i ON i.request_id=o.request_id
       ORDER BY o.created_at DESC, o.id DESC LIMIT :limit'
    );
    $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return $statement->fetchAll(PDO::FETCH_ASSOC);
  }
}
