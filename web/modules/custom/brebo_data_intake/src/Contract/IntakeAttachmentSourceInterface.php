<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Contract;

interface IntakeAttachmentSourceInterface {

  /**
   * Resolves one canonical permanent intake file.
   *
   * @return array{path:string,filename:string}|null
   */
  public function resolve(int $fileId): ?array;

}
