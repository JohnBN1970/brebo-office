<?php

declare(strict_types=1);

namespace Drupal\brebo_project_publication\Service;

use Drupal\brebo_project_publication\Contract\PublicProjectPublicationReadRepositoryInterface;

/**
 * Read-only projection of explicitly released project presentation data.
 *
 * This service is the only intended source for the Integration API. It never
 * reads arbitrary project fields: the bounded publication tables are the
 * external disclosure boundary.
 */
final class PublicProjectProjection {

  public function __construct(
    private readonly PublicProjectPublicationReadRepositoryInterface $repository,
  ) {}

  /**
   * Returns all externally released project projections.
   */
  public function all(): array {
    return array_map(fn(array $row): array => $this->project($row), $this->repository->releasedProjects());
  }

  /**
   * Returns one released project by stable public id, or NULL when unavailable.
   */
  public function byPublicId(string $publicId): ?array {
    $publicId = trim($publicId);
    if ($publicId === '') {
      return NULL;
    }

    $row = $this->repository->releasedProjectByPublicId($publicId);
    return $row === NULL ? NULL : $this->project($row);
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
    return $this->repository->approvedMedia($publicationId);
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


}
