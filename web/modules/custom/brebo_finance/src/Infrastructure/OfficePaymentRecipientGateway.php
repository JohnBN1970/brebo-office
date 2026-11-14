<?php
declare(strict_types=1);
namespace Drupal\brebo_finance\Infrastructure;

use Brebo\Office\Finance\OfficeSupplierRecipientResolver;
use Drupal\brebo_finance\Contract\PaymentRecipientGatewayInterface;
use RuntimeException;

/**
 * Inactive integration adapter: intentionally NOT registered as a Drupal service.
 * Caller must supply Office-owned repository callbacks and an Office authorization
 * callback. No Moneybird calls, no database writes, no production wiring.
 */
final class OfficePaymentRecipientGateway implements PaymentRecipientGatewayInterface {
  /** @var \Closure(int,int):bool */
  private \Closure $canReadInvoice;
  /** @var \Closure():int */
  private \Closure $clock;

  public function __construct(
    private readonly OfficeSupplierRecipientResolver $resolver,
    callable $canReadInvoice,
    callable $clock,
  ) {
    $this->canReadInvoice = \Closure::fromCallable($canReadInvoice);
    $this->clock = \Closure::fromCallable($clock);
  }

  public function snapshot(int $invoiceId, int $userId): array {
    if ($invoiceId <= 0 || $userId <= 0 || !($this->canReadInvoice)($invoiceId, $userId)) {
      throw new RuntimeException('Payment recipient access denied.');
    }
    return $this->resolver->snapshot($invoiceId, ($this->clock)());
  }

  public function unchanged(int $invoiceId, string $recipientHash, int $userId): bool {
    if ($invoiceId <= 0 || $userId <= 0 || !($this->canReadInvoice)($invoiceId, $userId)) {
      return false;
    }
    return $this->resolver->unchanged($invoiceId, $recipientHash, ($this->clock)());
  }
}
