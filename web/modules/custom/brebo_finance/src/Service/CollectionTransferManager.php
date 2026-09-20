<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/** Coordinates guarded, idempotent collection handoff and stores provider state. */
final class CollectionTransferManager {

  private const STORE = 'brebo_finance.collection_transfer';

  public function __construct(
    private readonly SalesInvoiceDebtorResolver $debtorResolver,
    private readonly CollectionDebtorProfileRepository $profileRepository,
    private readonly CollectionDossierBuilder $dossierBuilder,
    private readonly CollectionProviderInterface $provider,
    private readonly KeyValueFactoryInterface $keyValueFactory,
  ) {}

  /** @return array<string,mixed> */
  public function transfer(int $salesInvoiceId, int $actorUid): array {
    $existing = $this->state($salesInvoiceId);
    if (trim((string) ($existing['external_id'] ?? '')) !== '') return $existing;

    $relation = $this->debtorResolver->resolve($salesInvoiceId);
    $organizationId = (int) $relation['organization_id'];
    $profile = $this->profileRepository->get($organizationId);
    if (!$this->profileRepository->complete($organizationId)) {
      throw new \RuntimeException('Gestructureerde incasso-NAW van de debiteur is nog onvolledig.');
    }
    $profile['email'] = (string) $relation['email'];
    if (($profile['type'] ?? 'business') === 'business' && trim((string) ($profile['company_name'] ?? '')) === '') {
      $profile['company_name'] = (string) $relation['name'];
    }
    $dossier = $this->dossierBuilder->build($salesInvoiceId, $profile);
    if (!$this->provider->available()) throw new \RuntimeException('De geconfigureerde incassoprovider is niet beschikbaar.');
    $response = $this->provider->submit($dossier);
    $state = [
      'sales_invoice_id' => $salesInvoiceId,
      'organization_id' => $organizationId,
      'provider' => (string) ($response['provider'] ?? $this->provider->id()),
      'external_id' => (string) ($response['external_id'] ?? ''),
      'status' => (string) ($response['status'] ?? 'submitted'),
      'submitted_at' => (int) ($response['submitted_at'] ?? time()),
      'submitted_by' => $actorUid,
      'dossier_hash' => hash('sha256', json_encode($dossier, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
      'last_checked' => NULL,
      'paid_amount' => NULL,
    ];
    if ($state['external_id'] === '') throw new \RuntimeException('Incassoprovider gaf geen dossier-id terug.');
    $this->keyValueFactory->get(self::STORE)->set((string) $salesInvoiceId, $state);
    return $state;
  }

  /** @return array<string,mixed> */
  public function refresh(int $salesInvoiceId): array {
    $state = $this->state($salesInvoiceId);
    $externalId = trim((string) ($state['external_id'] ?? ''));
    if ($externalId === '') throw new \RuntimeException('Dit factuurdossier is nog niet extern overgedragen.');
    $remote = $this->provider->status($externalId);
    $state['status'] = (string) ($remote['status'] ?? $state['status'] ?? 'unknown');
    $state['paid_amount'] = isset($remote['paid_amount']) ? (string) $remote['paid_amount'] : ($state['paid_amount'] ?? NULL);
    $state['last_checked'] = (int) ($remote['updated_at'] ?? time());
    $this->keyValueFactory->get(self::STORE)->set((string) $salesInvoiceId, $state);
    return $state;
  }

  /** @return array<string,mixed> */
  public function state(int $salesInvoiceId): array {
    $value = $this->keyValueFactory->get(self::STORE)->get((string) $salesInvoiceId, []);
    return is_array($value) ? $value : [];
  }
}
