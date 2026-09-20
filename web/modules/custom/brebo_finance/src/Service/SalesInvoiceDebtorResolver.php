<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\node\NodeInterface;

/** Resolves the canonical debtor relation for a finalized sales invoice. */
final class SalesInvoiceDebtorResolver {

  public function __construct(
    private readonly Connection $database,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /** @return array{organization_id:int,name:string,email:string,draft_id:int} */
  public function resolve(int $salesInvoiceId): array {
    $invoice = $this->database->select('brebo_finance_sales_invoice', 'i')
      ->fields('i', ['invoice_number'])
      ->condition('id', $salesInvoiceId)
      ->execute()
      ->fetchAssoc();
    if ($invoice === FALSE || trim((string) $invoice['invoice_number']) === '') {
      throw new \RuntimeException('Definitieve verkoopfactuur is niet beschikbaar.');
    }

    $outbox = $this->database->select('brebo_finance_sales_invoice_outbox', 'o')
      ->fields('o', ['draft_id', 'payload'])
      ->condition('command_type', 'sales_invoice.register')
      ->orderBy('created', 'DESC')
      ->execute();
    $draftId = 0;
    foreach ($outbox as $row) {
      $payload = json_decode((string) $row->payload, TRUE);
      if (is_array($payload) && (string) ($payload['source']['invoice_number'] ?? '') === (string) $invoice['invoice_number']) {
        $draftId = (int) $row->draft_id;
        break;
      }
    }
    if ($draftId <= 0) {
      throw new \RuntimeException('Canonieke bron van deze verkoopfactuur kon niet worden teruggevonden.');
    }

    $context = $this->keyValueFactory->get('brebo_finance.sales_invoice_draft_context')->get((string) $draftId, []);
    $organizationId = is_array($context) ? (int) ($context['customer_organization_nid'] ?? 0) : 0;
    $organization = $organizationId > 0 ? $this->entityTypeManager->getStorage('node')->load($organizationId) : NULL;
    if (!$organization instanceof NodeInterface || $organization->bundle() !== 'brebo_organization') {
      throw new \RuntimeException('Canonieke debiteurrelatie ontbreekt.');
    }
    $email = $organization->hasField('field_brebo_org_email') ? trim((string) $organization->get('field_brebo_org_email')->value) : '';
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === FALSE) {
      throw new \RuntimeException('Debiteur heeft geen geldig centraal e-mailadres.');
    }

    return ['organization_id' => $organizationId, 'name' => (string) $organization->label(), 'email' => $email, 'draft_id' => $draftId];
  }
}
