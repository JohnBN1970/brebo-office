<?php

declare(strict_types=1);

namespace Drupal\brebo_project_publication\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Read-only projection of explicitly released project presentation data.
 *
 * This service is the only intended source for the Integration API. It never
 * reads arbitrary project fields: the bounded publication tables are the
 * external disclosure boundary.
 */
final class PublicProjectProjection {

  public function __construct(
    private readonly Connection $database,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Returns all externally released project projections.
   */
  public function all(): array {
    if (!$this->storageAvailable()) {
      return [];
    }

    $rows = $this->database->select('brebo_project_publication', 'p')
      ->fields('p')
      ->condition('external_release', 1)
      ->condition('review_status', 'approved')
      ->orderBy('changed', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    return array_map(fn(array $row): array => $this->project($row), $rows);
  }

  /**
   * Returns one released project by stable public id, or NULL when unavailable.
   */
  public function byPublicId(string $publicId): ?array {
    $publicId = trim($publicId);
    if ($publicId === '' || !$this->storageAvailable()) {
      return NULL;
    }

    $row = $this->database->select('brebo_project_publication', 'p')
      ->fields('p')
      ->condition('public_id', $publicId)
      ->condition('external_release', 1)
      ->condition('review_status', 'approved')
      ->execute()
      ->fetchAssoc();

    return $row === FALSE ? NULL : $this->project($row);
  }

  private function project(array $row): array {
    return [
      'public_id' => (string) $row['public_id'],
      'slug' => $this->nullableString($row['public_slug'] ?? NULL),
      'title' => $this->nullableString($row['public_title'] ?? NULL),
      'intro' => $this->nullableString($row['public_intro'] ?? NULL),
      'building_question' => $this->nullableString($row['building_question'] ?? NULL),
      'chosen_approach' => $this->nullableString($row['chosen_approach'] ?? NULL),
      'realized_results' => $this->jsonList($row['realized_results'] ?? NULL),
      'lens_roles' => $this->jsonList($row['lens_roles_json'] ?? NULL),
      'status' => $this->nullableString($row['public_status'] ?? NULL),
      'media' => $this->media((int) $row['id']),
      'publication_version' => (int) $row['publication_version'],
      'updated_at' => (int) $row['changed'],
    ];
  }

  private function media(int $publicationId): array {
    if (!$this->database->schema()->tableExists('brebo_project_publication_media')) {
      return [];
    }

    $query = $this->database->select('brebo_project_publication_media', 'm');
    $query->innerJoin('file_managed', 'f', 'f.fid = m.file_id');
    $query->fields('m', ['file_id', 'alt_text', 'sort_weight']);
    $query->addField('f', 'uri');
    $rows = $query
      ->condition('m.publication_id', $publicationId)
      ->condition('m.approved', 1)
      ->orderBy('m.sort_weight', 'ASC')
      ->orderBy('m.id', 'ASC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $media = [];
    foreach ($rows as $row) {
      $media[] = [
        'id' => (int) $row['file_id'],
        'url' => $this->fileUrlGenerator->generateAbsoluteString((string) $row['uri']),
        'alt' => $this->nullableString($row['alt_text'] ?? NULL),
      ];
    }
    return $media;
  }

  private function jsonList(mixed $value): array {
    if (!is_string($value) || trim($value) === '') {
      return [];
    }
    $decoded = json_decode($value, TRUE);
    if (is_array($decoded)) {
      return array_values(array_filter($decoded, static fn(mixed $item): bool => is_string($item) && trim($item) !== ''));
    }
    // Existing rows may predate the JSON convention. Keep their public text
    // usable without exposing any additional Office fields.
    return [trim($value)];
  }

  private function nullableString(mixed $value): ?string {
    if (!is_string($value)) {
      return NULL;
    }
    $value = trim($value);
    return $value === '' ? NULL : $value;
  }

  private function storageAvailable(): bool {
    return $this->database->schema()->tableExists('brebo_project_publication');
  }

}
