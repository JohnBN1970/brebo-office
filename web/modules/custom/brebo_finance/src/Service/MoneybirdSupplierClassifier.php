<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

/**
 * Deterministic, mutation-free classifier for Moneybird supplier contacts.
 */
final class MoneybirdSupplierClassifier {

  /**
   * @param array<int, array<string, mixed>> $invoices
   *   Normalized purchase invoices from the BREBO Integration API.
   *
   * @return array<string, mixed>
   *   Classification summary and unique contacts.
   */
  public function classify(array $invoices): array {
    $contacts = [];

    foreach ($invoices as $invoice) {
      $contactId = trim((string) ($invoice['contact_id'] ?? ''));
      $name = trim((string) ($invoice['supplier_name'] ?? ''));
      if ($contactId === '' || $name === '') {
        continue;
      }

      $key = $contactId;
      if (!isset($contacts[$key])) {
        $contacts[$key] = [
          'contact_id' => $contactId,
          'name' => $name,
          'invoice_count' => 0,
          'supplier_contact' => is_array($invoice['supplier_contact'] ?? NULL) ? $invoice['supplier_contact'] : [],
        ];
      }
      $contacts[$key]['invoice_count']++;

      if ($contacts[$key]['supplier_contact'] === [] && is_array($invoice['supplier_contact'] ?? NULL)) {
        $contacts[$key]['supplier_contact'] = $invoice['supplier_contact'];
      }
    }

    $result = [
      'invoice_count' => count($invoices),
      'unique_contacts' => count($contacts),
      'supplier' => [],
      'receipt' => [],
      'review' => [],
    ];

    foreach ($contacts as $contact) {
      $classification = $this->classifyContact($contact);
      $result[strtolower($classification['classification'])][] = $classification;
    }

    foreach (['supplier', 'receipt', 'review'] as $group) {
      usort($result[$group], static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']));
      $result[$group . '_count'] = count($result[$group]);
    }

    return $result;
  }

  /** @param array<string, mixed> $contact */
  private function classifyContact(array $contact): array {
    $name = trim((string) $contact['name']);
    $normalized = mb_strtolower($name);
    $master = is_array($contact['supplier_contact'] ?? NULL) ? $contact['supplier_contact'] : [];

    // Explicit BREBO merchant rules. These are deliberately narrow: a receipt
    // classification suppresses CRM organization creation, so uncertainty must
    // remain REVIEW rather than being guessed.
    $receiptRules = [
      '. turnhout' => ['category' => 'Tankstation', 'reason' => 'confirmed_receipt_merchant'],
      'action' => ['category' => 'Winkel', 'reason' => 'retail_receipt_merchant'],
    ];
    foreach ($receiptRules as $needle => $rule) {
      if ($normalized === $needle || str_starts_with($normalized, $needle . ' ')) {
        return $this->item($contact, 'RECEIPT', $rule['reason'], $rule['category']);
      }
    }

    $companyName = trim((string) ($master['company_name'] ?? ''));
    $kvk = trim((string) ($master['chamber_of_commerce'] ?? ''));
    $vat = trim((string) ($master['tax_number'] ?? ''));
    $email = trim((string) ($master['email'] ?? ''));
    $address = trim((string) ($master['address1'] ?? ''));
    $city = trim((string) ($master['city'] ?? ''));

    // Strong business identity: safe candidate for the central CRM supplier
    // organization. KVK/VAT is strongest; otherwise require a company name and
    // at least one additional business contact/address signal.
    if ($kvk !== '' || $vat !== '') {
      return $this->item($contact, 'SUPPLIER', 'business_registration_present');
    }
    if ($companyName !== '' && ($email !== '' || $address !== '' || $city !== '')) {
      return $this->item($contact, 'SUPPLIER', 'company_masterdata_present');
    }

    // Repeated invoices are useful evidence but not enough to mutate CRM data
    // without a business identity. Keep them visible for one human decision.
    if ((int) ($contact['invoice_count'] ?? 0) > 1) {
      return $this->item($contact, 'REVIEW', 'repeated_without_business_identity');
    }

    return $this->item($contact, 'REVIEW', 'insufficient_business_identity');
  }

  /** @param array<string, mixed> $contact */
  private function item(array $contact, string $classification, string $reason, ?string $category = NULL): array {
    $item = [
      'contact_id' => (string) $contact['contact_id'],
      'name' => (string) $contact['name'],
      'invoice_count' => (int) ($contact['invoice_count'] ?? 0),
      'classification' => $classification,
      'reason' => $reason,
    ];
    if ($category !== NULL) {
      $item['transaction_category'] = $category;
    }
    return $item;
  }

}
