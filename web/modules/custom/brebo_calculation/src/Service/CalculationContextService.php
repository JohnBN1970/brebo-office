<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\brebo_calculation\Contract\CalculationContextRepositoryInterface;

/** BREBO-owned calculation identity/context read-write service. */
final class CalculationContextService {

  public function __construct(
    private readonly CalculationContextRepositoryInterface $repository,
  ) {}

  /** @return array<string,mixed>|null */
  public function get(int $calculationId): ?array {
    return $this->repository->get($calculationId);
  }

  /** @param array<string,mixed> $context */
  public function upsert(int $calculationId, array $context): void {
    if ($calculationId <= 0) {
      throw new \InvalidArgumentException('Calculation id is required.');
    }
    $this->repository->upsert($calculationId, [
        'code' => ($context['code'] ?? '') !== '' ? mb_substr((string) $context['code'], 0, 64) : NULL,
        'label' => mb_substr((string) ($context['label'] ?? ('Calculatie ' . $calculationId)), 0, 255),
        'package_id' => !empty($context['package_id']) ? (int) $context['package_id'] : NULL,
        'project_id' => !empty($context['project_id']) ? (int) $context['project_id'] : NULL,
        'project_label' => ($context['project_label'] ?? '') !== '' ? mb_substr((string) $context['project_label'], 0, 255) : NULL,
        'updated' => time(),
      ]);
  }

}
