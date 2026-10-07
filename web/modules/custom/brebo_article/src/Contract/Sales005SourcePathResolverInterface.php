<?php

declare(strict_types=1);

namespace Drupal\brebo_article\Contract;

/** Resolves an import URI to a local readable path. */
interface Sales005SourcePathResolverInterface {

  public function resolve(string $uri): string;

}
