<?php

declare(strict_types=1);

namespace Drupal\brebo_article\Infrastructure;

use Drupal\brebo_article\Contract\Sales005SourcePathResolverInterface;
use Drupal\Core\File\FileSystemInterface;

/** Drupal filesystem adapter for SALES005 source paths. */
final class DrupalSales005SourcePathResolver implements Sales005SourcePathResolverInterface {

  public function __construct(private readonly FileSystemInterface $fileSystem) {}

  public function resolve(string $uri): string {
    return $this->fileSystem->realpath($uri) ?: $uri;
  }

}
