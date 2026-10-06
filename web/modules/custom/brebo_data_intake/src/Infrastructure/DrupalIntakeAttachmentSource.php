<?php

declare(strict_types=1);

namespace Drupal\brebo_data_intake\Infrastructure;

use Drupal\brebo_data_intake\Contract\IntakeAttachmentSourceInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;

final class DrupalIntakeAttachmentSource implements IntakeAttachmentSourceInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileSystemInterface $fileSystem,
  ) {}

  public function resolve(int $fileId): ?array {
    if ($fileId <= 0) {
      return NULL;
    }

    $file = $this->entityTypeManager->getStorage('file')->load($fileId);
    if (!$file instanceof FileInterface || !$file->isPermanent()) {
      return NULL;
    }

    $uri = $file->getFileUri();
    if (!str_starts_with($uri, 'private://brebo-intake/')) {
      return NULL;
    }

    $realpath = $this->fileSystem->realpath($uri);
    if (!is_string($realpath) || $realpath === '' || !is_file($realpath) || !is_readable($realpath)) {
      return NULL;
    }

    return [
      'path' => $realpath,
      'filename' => $file->getFilename(),
    ];
  }

}
