<?php

declare(strict_types=1);

namespace Drupal\brebo_project_cockpit\Contract;

/** Persistence/read boundary for project invoice administration. */
interface ProjectInvoiceRepositoryInterface {

  public function draftStorageAvailable(): bool;

  public function releaseStorageAvailable(): bool;

  /** @return array<string,mixed> */
  public function projectContract(int $projectId): array;

  /** @return array{contract:array<string,mixed>,instalments:list<array<string,mixed>>,changes:list<array<string,mixed>>,provisional_sums:list<array<string,mixed>>,drafts:list<array<string,mixed>>,outbox:list<array<string,mixed>>,sales_invoices:list<array<string,mixed>>} */
  public function projectOverview(int $projectId): array;

  /** @return list<array<string,mixed>> */
  public function purchaseInvoices(int $projectId): array;

  /** @return list<array<string,mixed>> */
  public function salesInvoices(int $projectId): array;

  /** @return list<array<string,mixed>> */
  public function billableInstalments(int $projectId): array;

  /** @return list<array<string,mixed>> */
  public function invoiceableChanges(int $projectId): array;

  /** @return list<array<string,mixed>> */
  public function invoiceableProvisionalSums(int $projectId): array;

  public function instalmentEvidencePayload(int $projectId, int $instalmentId): ?string;

  /** @return list<array<string,mixed>> */
  public function sourceLines(int $projectId, string $type, int $id): array;

  /** @param array<string,mixed> $draftFields @param list<array<string,mixed>> $lineFields */
  public function createDraft(array $draftFields, array $lineFields): int;

  /** @return array<string,mixed>|null */
  public function draftForProject(int $draftId, int $projectId, bool $draftOnly = FALSE): ?array;

  /** @return list<array<string,mixed>> */
  public function draftLines(int $draftId): array;

  /** @param array<string,mixed> $outboxFields @param array<string,mixed> $draftFields */
  public function queueRelease(int $draftId, int $projectId, array $outboxFields, array $draftFields): int;

  /** @return list<float> */
  public function instalmentVatRates(int $instalmentId): array;

}
