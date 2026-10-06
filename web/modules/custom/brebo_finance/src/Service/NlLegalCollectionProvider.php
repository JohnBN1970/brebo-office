<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\Contract\CollectionProviderRuntimeConfigInterface;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/** nl.legal REST adapter behind the provider-neutral collection boundary. */
final class NlLegalCollectionProvider implements CollectionProviderInterface {

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly CollectionProviderRuntimeConfigInterface $runtimeConfig,
  ) {}

  public function id(): string {
    return 'nllegal';
  }

  public function available(): bool {
    return $this->runtimeConfig->apiKey() !== '';
  }

  public function submit(array $dossier): array {
    if (!$this->available()) {
      throw new \RuntimeException('nl.legal API-key ontbreekt in de runtimeconfiguratie.');
    }

    $this->assertDossier($dossier);
    $idempotencyKey = trim((string) ($dossier['idempotency_key'] ?? ''));
    if ($idempotencyKey === '') {
      $idempotencyKey = hash('sha256', json_encode($dossier, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    $response = $this->httpClient->request('POST', $this->runtimeConfig->baseUrl() . '/orders', [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->runtimeConfig->apiKey(),
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'Idempotency-Key' => $idempotencyKey,
      ],
      'json' => $this->payload($dossier),
      'timeout' => 60,
      'http_errors' => FALSE,
    ]);

    $data = $this->decode($response);
    if ($response->getStatusCode() !== 201) {
      throw new \RuntimeException('nl.legal overdracht mislukt: HTTP ' . $response->getStatusCode() . ' - ' . $this->errorMessage($data));
    }

    $externalId = trim((string) ($data['id'] ?? ''));
    if ($externalId === '') {
      throw new \RuntimeException('nl.legal response bevat geen order-id.');
    }

    return [
      'provider' => $this->id(),
      'external_id' => $externalId,
      'status' => (string) ($data['case']['case_number'] ?? $data['mode'] ?? 'submitted'),
      'submitted_at' => time(),
    ];
  }

  public function status(string $externalId): array {
    $externalId = trim($externalId);
    if ($externalId === '') {
      throw new \InvalidArgumentException('External collection id is required.');
    }
    if (!$this->available()) {
      throw new \RuntimeException('nl.legal API-key ontbreekt in de runtimeconfiguratie.');
    }

    $response = $this->httpClient->request('GET', $this->runtimeConfig->baseUrl() . '/orders/' . rawurlencode($externalId), [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->runtimeConfig->apiKey(),
        'Accept' => 'application/json',
      ],
      'timeout' => 30,
      'http_errors' => FALSE,
    ]);
    $data = $this->decode($response);
    if ($response->getStatusCode() !== 200) {
      throw new \RuntimeException('nl.legal statusopvraag mislukt: HTTP ' . $response->getStatusCode() . ' - ' . $this->errorMessage($data));
    }

    $paid = NULL;
    if (isset($data['case']['outstanding_amount_cents'], $data['total_amount_cents'])) {
      $paidCents = max(0, (int) $data['total_amount_cents'] - (int) $data['case']['outstanding_amount_cents']);
      $paid = number_format($paidCents / 100, 2, '.', '');
    }

    $result = [
      'external_id' => $externalId,
      'status' => (string) ($data['case']['status'] ?? $data['status'] ?? 'unknown'),
      'updated_at' => time(),
    ];
    if ($paid !== NULL) {
      $result['paid_amount'] = $paid;
    }
    return $result;
  }

  /** @return array<string,mixed> */
  private function payload(array $dossier): array {
    $payload = [
      'type' => (string) ($dossier['type'] ?? 'incasso'),
      'reference' => (string) $dossier['reference'],
      'title' => (string) ($dossier['title'] ?? ''),
      'description' => (string) ($dossier['description'] ?? ''),
      'debtors' => [$dossier['debtor']],
      'invoices' => [$dossier['invoice']],
    ];
    if (!empty($dossier['attachments'])) {
      $payload['attachments'] = array_values($dossier['attachments']);
    }
    return $payload;
  }

  private function assertDossier(array $dossier): void {
    foreach (['reference', 'debtor', 'invoice'] as $required) {
      if (!isset($dossier[$required]) || $dossier[$required] === '' || $dossier[$required] === []) {
        throw new \InvalidArgumentException('Incassodossier mist verplicht veld: ' . $required . '.');
      }
    }
    $debtor = is_array($dossier['debtor']) ? $dossier['debtor'] : [];
    $address = is_array($debtor['address'] ?? NULL) ? $debtor['address'] : [];
    foreach (['street', 'house_number', 'postal_code', 'city'] as $required) {
      if (trim((string) ($address[$required] ?? '')) === '') {
        throw new \InvalidArgumentException('Incassodossier mist gestructureerd debiteuradres: ' . $required . '.');
      }
    }
    $invoice = is_array($dossier['invoice']) ? $dossier['invoice'] : [];
    foreach (['invoice_number', 'invoice_date', 'due_date', 'amount_cents'] as $required) {
      if (!isset($invoice[$required]) || $invoice[$required] === '') {
        throw new \InvalidArgumentException('Incassodossier mist factuurveld: ' . $required . '.');
      }
    }
  }

  /** @return array<string,mixed> */
  private function decode(ResponseInterface $response): array {
    $body = trim((string) $response->getBody());
    if ($body === '') {
      return [];
    }
    $decoded = json_decode($body, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /** @param array<string,mixed> $data */
  private function errorMessage(array $data): string {
    return trim((string) ($data['detail'] ?? $data['message'] ?? $data['title'] ?? 'Onbekende fout'));
  }
}
