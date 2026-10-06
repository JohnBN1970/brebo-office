<?php

declare(strict_types=1);

namespace Drupal\brebo_project_publication\Contract;

interface PublicProjectPublicationReadRepositoryInterface {

  public function storageAvailable(): bool;

  /** @return array<int,array<string,mixed>> */
  public function releasedProjects(): array;

  /** @return array<string,mixed>|null */
  public function releasedProjectByPublicId(string $publicId): ?array;

  /** @return array<int,array{id:int,url:string,alt:?string}> */
  public function approvedMedia(int $publicationId): array;

}
