<?php

declare(strict_types=1);

namespace Drupal\brebo_contract_control\Service;

use Drupal\brebo_contract_control\Contract\ManagementActionSourceReadRepositoryInterface;

/** Resolves a management action to its underlying operational source data. */
final class ManagementActionSourceResolver {

  public function __construct(
    private readonly ManagementActionSourceReadRepositoryInterface $repository,
  ) {}

  /** @return array<string, mixed> */
  public function resolve(int $actionId): array {
    $action = $this->repository->findAction($actionId);
    if (!$action) {
      throw new \InvalidArgumentException('Onbekende managementactie.');
    }

    $context = json_decode((string) ($action['context_json'] ?? '{}'), TRUE) ?: [];
    $sourceType = (string) ($action['source_type'] ?? '');
    $items = match ($sourceType) {
      'controller_case' => $this->repository->findOpenCriticalControllerCases(),
      'payment_control' => $this->repository->findBlockedInvoices(),
      'contract_obligation' => $this->repository->findOverdueObligations(time()),
      'supplier_risk' => array_values((array) ($context['supplier_risk'] ?? [])),
      'portfolio_risk' => [(array) ($context['portfolio'] ?? [])],
      default => [],
    };

    return [
      'action' => $action,
      'source_type' => $sourceType,
      'items' => $items,
      'context' => $context,
    ];
  }

}
