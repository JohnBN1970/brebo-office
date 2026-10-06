<?php

declare(strict_types=1);

namespace Drupal\brebo_project_publication\Infrastructure;

use Drupal\brebo_project_publication\Contract\PublicProjectPublicationReadRepositoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\File\FileUrlGeneratorInterface;

final class DatabasePublicProjectPublicationReadRepository implements PublicProjectPublicationReadRepositoryInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  public function storageAvailable(): bool {
    return $this->database->schema()->tableExists('brebo_project_publication');
  }

  public function releasedProjects(): array {
    if (!$this->storageAvailable()) {
      return [];
    }

    return $this->database->select('brebo_project_publication', 'p')
      ->fields('p')
      ->condition('external_release', 1)
      ->condition('review_status', 'approved')
      ->orderBy('changed', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  public function releasedProjectByPublicId(string $publicId): ?array {
    if (!$this->storageAvailable()) {
      return NULL;
    }

    $row = $this->database->select('brebo_project_publication', 'p')
      ->fields('p')
      ->condition('public_id', $publicId)
      ->condition('external_release', 1)
      ->condition('review_status', 'approved')
      ->execute()
      ->fetchAssoc();

    return $row === FALSE ? NULL : $row;
  }

  public function approvedMedia(int $publicationId): array {
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
    foreach ($rows ?: [] as $row) {
      $alt = isset($row['alt_text']) && is_string($row['alt_text']) ? trim($row['alt_text']) : '';
      $media[] = [
        'id' => (int) $row['file_id'],
        'url' => $this->fileUrlGenerator->generateAbsoluteString((string) $row['uri']),
        'alt' => $alt !== '' ? $alt : NULL,
      ];
    }
    return $media;
  }

}
