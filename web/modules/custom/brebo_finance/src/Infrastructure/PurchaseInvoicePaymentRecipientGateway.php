<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PaymentRecipientGatewayInterface;
use Drupal\brebo_finance\Service\PurchaseInvoiceImporter;

final class PurchaseInvoicePaymentRecipientGateway implements PaymentRecipientGatewayInterface {
  public function __construct(private readonly PurchaseInvoiceImporter $importer) {}
  public function snapshot(int $invoiceId,int $userId): array {return$this->importer->paymentRecipientSnapshot($invoiceId,$userId);}
  public function unchanged(int $invoiceId,string $recipientHash,int $userId): bool {return$this->importer->paymentRecipientUnchanged($invoiceId,$recipientHash,$userId);}
}
