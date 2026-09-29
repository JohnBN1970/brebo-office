<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Infrastructure;

use Drupal\brebo_finance\Contract\PurchaseInvoiceReadbackGatewayInterface;
use Drupal\brebo_finance\Service\PurchaseInvoiceIntegrationClient;

final class MoneybirdPurchaseInvoiceReadbackGateway implements PurchaseInvoiceReadbackGatewayInterface {
  public function __construct(private readonly PurchaseInvoiceIntegrationClient $client) {}
  public function all(): array { return $this->client->fetchAll(); }
}
