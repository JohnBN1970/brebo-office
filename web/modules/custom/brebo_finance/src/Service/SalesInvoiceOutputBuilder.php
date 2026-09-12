<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_office_core\Service\SimplePdfRenderer;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\node\NodeInterface;

/** Builds sales-invoice output from the canonical invoice draft. */
final class SalesInvoiceOutputBuilder {

  public function __construct(
    private readonly Connection $database,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SimplePdfRenderer $pdfRenderer,
  ) {}

  /** @return array{content:string,filename:string,hash:string} */
  public function conceptPdf(int $draftId): array {
    $draft = $this->loadDraft($draftId, TRUE);
    [$context, $organization] = $this->contextAndOrganization($draftId);
    $lines = $this->invoiceLines($draft, $context, $organization, 'Conceptnummer', (string) $draft['draft_number']);
    array_unshift($lines, 'CONCEPT / TER BEOORDELING', '');
    $lines[] = '';
    $lines[] = 'Dit document is uitsluitend een concept ter beoordeling en is geen definitieve factuur.';

    $content = $this->pdfRenderer->render('BREBO - Conceptfactuur', $lines, 'CONCEPT');
    return [
      'content' => $content,
      'filename' => preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $draft['draft_number']) . '-concept.pdf',
      'hash' => hash('sha256', $content),
    ];
  }

  /** @return array{content:string,filename:string,hash:string} */
  public function finalPdf(int $draftId, string $invoiceNumber): array {
    $invoiceNumber = trim($invoiceNumber);
    if ($invoiceNumber === '') {
      throw new \InvalidArgumentException('Definitief factuurnummer ontbreekt.');
    }
    $draft = $this->loadDraft($draftId, FALSE);
    [$context, $organization] = $this->contextAndOrganization($draftId);
    $lines = $this->invoiceLines($draft, $context, $organization, 'Factuurnummer', $invoiceNumber);
    $lines[] = '';
    $lines[] = 'Deze factuur is definitief uitgegeven door BREBO Office.';

    $content = $this->pdfRenderer->render('BREBO - Factuur ' . $invoiceNumber, $lines);
    return [
      'content' => $content,
      'filename' => preg_replace('/[^A-Za-z0-9._-]+/', '-', $invoiceNumber) . '.pdf',
      'hash' => hash('sha256', $content),
    ];
  }

  /** @return array{0:array<string,mixed>,1:NodeInterface} */
  private function contextAndOrganization(int $draftId): array {
    $context = $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->get((string) $draftId, []);
    if (!is_array($context) || empty($context['customer_organization_nid'])) {
      throw new \RuntimeException('Factuurconcept heeft geen canonieke debiteur.');
    }
    $organization = $this->entityTypeManager->getStorage('node')->load((int) $context['customer_organization_nid']);
    if (!$organization instanceof NodeInterface || $organization->bundle() !== 'brebo_organization') {
      throw new \RuntimeException('Canonieke debiteur is niet beschikbaar.');
    }
    return [$context, $organization];
  }

  /** @return string[] */
  private function invoiceLines(array $draft, array $context, NodeInterface $organization, string $numberLabel, string $number): array {
    $lines = [
      $numberLabel . ': ' . $number,
      'Debiteur: ' . (string) $organization->label(),
      'Factuurdatum: ' . (string) $draft['invoice_date'],
      'Vervaldatum: ' . (string) $draft['due_date'],
      'Klantreferentie: ' . (trim((string) ($context['customer_ref'] ?? '')) ?: '-'),
      'Omschrijving: ' . (string) $draft['description'],
      '',
      'Factuurregels',
    ];
    foreach ((array) ($context['lines'] ?? []) as $line) {
      $quantity = rtrim(rtrim(number_format((float) ($line['quantity'] ?? 1), 4, '.', ''), '0'), '.');
      $unit = trim((string) ($line['unit'] ?? ''));
      $description = trim((string) ($line['description'] ?? ''));
      $amount = number_format((float) ($line['amount_ex_vat'] ?? 0), 2, ',', '.');
      $vat = rtrim(rtrim(number_format((float) ($line['vat_rate'] ?? 0), 2, ',', ''), '0'), ',');
      $lines[] = $quantity . ' ' . $unit . '  ' . $description . '  EUR ' . $amount . ' excl. btw  (' . $vat . '% btw)';
    }
    $lines[] = '';
    $lines[] = 'Totaal excl. btw: EUR ' . number_format((float) $draft['amount_ex_vat'], 2, ',', '.');
    $lines[] = 'Btw: EUR ' . number_format((float) $draft['vat_amount'], 2, ',', '.');
    $lines[] = 'Totaal incl. btw: EUR ' . number_format((float) $draft['amount_inc_vat'], 2, ',', '.');
    return $lines;
  }

  /** @return array<string,mixed> */
  private function loadDraft(int $draftId, bool $mustBeDraft): array {
    $query = $this->database->select('brebo_finance_sales_invoice_draft', 'd')
      ->fields('d')
      ->condition('id', $draftId);
    if ($mustBeDraft) {
      $query->condition('status', 'draft');
    }
    $draft = $query->execute()->fetchAssoc();
    if ($draft === FALSE) {
      throw new \InvalidArgumentException('Factuurconcept niet gevonden.');
    }
    return $draft;
  }

}
