<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;

/** Builds the immutable BREBO snapshot transferred to a collection provider. */
final class CollectionDossierBuilder {

  public function __construct(
    private readonly Connection $database,
    private readonly ReceivablesDunningManager $dunningManager,
  ) {}

  /**
   * @param array<string,mixed> $debtor
   *   Structured debtor snapshot. Address must be split into street,
   *   house_number, postal_code and city before transfer is allowed.
   * @param array<int,array<string,mixed>> $attachments
   *   Optional immutable provider attachments.
   *
   * @return array<string,mixed>
   */
  public function build(int $invoiceId, array $debtor, array $attachments = []): array {
    if ($invoiceId <= 0) {
      throw new \InvalidArgumentException('Sales invoice id is required.');
    }

    $invoice = $this->database->select('brebo_finance_sales_invoice', 'i')
      ->fields('i', ['id', 'invoice_number', 'project_nid', 'invoice_date', 'due_date', 'status', 'amount_inc_vat', 'paid_amount_inc_vat'])
      ->condition('id', $invoiceId)
      ->execute()
      ->fetchAssoc();
    if ($invoice === FALSE) {
      throw new \InvalidArgumentException('Verkoopfactuur niet gevonden.');
    }

    $state = $this->dunningManager->state($invoiceId);
    if ((string) ($state['status'] ?? '') !== 'gereed_voor_incasso') {
      throw new \RuntimeException('Factuur is nog niet expliciet gereed voor incasso.');
    }
    if (($state['blocked_reason'] ?? NULL) !== NULL) {
      throw new \RuntimeException('Incasso-overdracht is geblokkeerd: ' . (string) $state['blocked_reason'] . '.');
    }

    $outstanding = round((float) ($state['outstanding_amount_inc_vat'] ?? 0), 2);
    if ($outstanding <= 0) {
      throw new \RuntimeException('Er staat geen positief bedrag meer open voor incasso.');
    }

    $debtor = $this->normalizeDebtor($debtor);
    $invoiceNumber = trim((string) $invoice['invoice_number']);
    if ($invoiceNumber === '') {
      throw new \RuntimeException('Definitief factuurnummer ontbreekt.');
    }

    $snapshot = [
      'schema' => 'brebo.collection.dossier.v1',
      'type' => 'incasso',
      'reference' => $invoiceNumber,
      'title' => 'Onbetaalde BREBO-factuur ' . $invoiceNumber,
      'description' => 'Openstaande definitieve verkoopfactuur uit BREBO Office.',
      'debtor' => $debtor,
      'invoice' => [
        'invoice_number' => $invoiceNumber,
        'invoice_date' => (string) $invoice['invoice_date'],
        'due_date' => (string) $invoice['due_date'],
        'amount_cents' => (int) round($outstanding * 100),
        'description' => 'Openstaand saldo factuur ' . $invoiceNumber,
      ],
      'attachments' => array_values($attachments),
      'source' => [
        'system' => 'brebo-office',
        'sales_invoice_id' => (int) $invoice['id'],
        'project_nid' => (int) $invoice['project_nid'],
        'original_amount_inc_vat' => number_format((float) $invoice['amount_inc_vat'], 2, '.', ''),
        'paid_amount_inc_vat' => number_format((float) $invoice['paid_amount_inc_vat'], 2, '.', ''),
        'receivables_status' => (string) $state['status'],
      ],
      'created_at' => time(),
    ];
    $snapshot['idempotency_key'] = 'brebo-collection:' . $invoiceNumber . ':' . hash('sha256', json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    return $snapshot;
  }

  /** @param array<string,mixed> $debtor @return array<string,mixed> */
  private function normalizeDebtor(array $debtor): array {
    $type = (string) ($debtor['type'] ?? 'business');
    if (!in_array($type, ['business', 'individual'], TRUE)) {
      throw new \InvalidArgumentException('Ongeldig debiteurtype voor incasso.');
    }

    $address = is_array($debtor['address'] ?? NULL) ? $debtor['address'] : [];
    foreach (['street', 'house_number', 'postal_code', 'city'] as $field) {
      if (trim((string) ($address[$field] ?? '')) === '') {
        throw new \InvalidArgumentException('Gestructureerd debiteuradres is onvolledig: ' . $field . '.');
      }
    }

    if ($type === 'business' && trim((string) ($debtor['company_name'] ?? '')) === '') {
      throw new \InvalidArgumentException('Bedrijfsnaam ontbreekt voor zakelijke incasso.');
    }
    if ($type === 'individual' && (trim((string) ($debtor['last_name'] ?? '')) === '')) {
      throw new \InvalidArgumentException('Achternaam ontbreekt voor particuliere incasso.');
    }

    $normalized = [
      'type' => $type,
      'customer_number' => trim((string) ($debtor['customer_number'] ?? '')),
      'email' => trim((string) ($debtor['email'] ?? '')),
      'address' => [
        'street' => trim((string) $address['street']),
        'house_number' => trim((string) $address['house_number']),
        'postal_code' => trim((string) $address['postal_code']),
        'city' => trim((string) $address['city']),
        'country_code' => strtoupper(trim((string) ($address['country_code'] ?? 'NL'))),
      ],
    ];
    if ($type === 'business') {
      $normalized['company_name'] = trim((string) $debtor['company_name']);
    }
    else {
      $normalized['first_name'] = trim((string) ($debtor['first_name'] ?? ''));
      $normalized['last_name'] = trim((string) $debtor['last_name']);
    }
    return $normalized;
  }
}
