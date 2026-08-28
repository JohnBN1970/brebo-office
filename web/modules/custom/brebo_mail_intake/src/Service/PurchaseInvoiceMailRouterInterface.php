<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/** Optional bridge for controlled mailbox-to-purchase-invoice routing. */
interface PurchaseInvoiceMailRouterInterface {

  /**
   * Routes one already-normalized mail item after Communication persistence.
   *
   * Implementations must be idempotent and conservative: uncertain messages
   * remain Communication only and may never approve or release payment.
   *
   * @param array<string, mixed> $mail
   * @param array<string, mixed> $attachmentEvidence
   *
   * @return array<string, mixed>
   */
  public function route(array $mail, array $attachmentEvidence, int $communicationNid): array;

}
