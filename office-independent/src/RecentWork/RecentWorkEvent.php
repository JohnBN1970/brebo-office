<?php

declare(strict_types=1);

namespace Brebo\Office\RecentWork;

use DomainException;

/**
 * Validated, non-sensitive navigation event. Never accepts arbitrary URLs,
 * session secrets, launch tokens or client-supplied operator IDs.
 */
final class RecentWorkEvent {
  private const MODULES = [
    'projects' => ['project'],
    'calculation' => ['calculation'],
    'procurement' => ['purchase_order'],
    'finance' => ['invoice'],
  ];
  private const VIEWS = [
    'projects' => ['overview', 'detail'],
    'calculation' => ['overview', 'chapter'],
    'procurement' => ['overview', 'detail'],
    'finance' => ['overview', 'detail'],
  ];

  public function __construct(
    public readonly string $module,
    public readonly string $resourceType,
    public readonly int $resourceId,
    public readonly string $viewKey,
    public readonly ?int $positionId = null,
  ) {
    if (!isset(self::MODULES[$module])
      || !in_array($resourceType, self::MODULES[$module], true)
      || !in_array($viewKey, self::VIEWS[$module], true)
      || $resourceId < 1
      || ($positionId !== null && $positionId < 1)
      || ($positionId !== null && $viewKey !== 'chapter')) {
      throw new DomainException('Invalid recent-work navigation event.');
    }
  }

  public function positionJson(): string {
    return json_encode(
      $this->positionId === null ? new \stdClass() : ['chapter_id' => $this->positionId],
      JSON_THROW_ON_ERROR
    );
  }
}
