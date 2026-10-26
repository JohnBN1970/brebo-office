<?php

declare(strict_types=1);

namespace Brebo\Office\RecentWork;

/**
 * Authorizes both recording and resuming an individual work item.
 * Fail closed until each module provides its own resource-level resolver.
 */
final class RecentWorkAccess {
  /** @var array<string, callable(string, string, int): bool> */
  private array $resolvers;

  /** @param array<string, callable(string, string, int): bool> $resolvers */
  public function __construct(array $resolvers = []) {
    $this->resolvers = $resolvers;
  }

  public function canAccess(string $operatorId, RecentWorkEvent $event): bool {
    if ($operatorId === '' || strlen($operatorId) > 128) {
      return false;
    }
    $resolver = $this->resolvers[$event->module] ?? null;
    if (!is_callable($resolver)) {
      return false;
    }
    try {
      return $resolver($operatorId, $event->resourceType, $event->resourceId) === true;
    } catch (\Throwable $e) {
      return false;
    }
  }
}
