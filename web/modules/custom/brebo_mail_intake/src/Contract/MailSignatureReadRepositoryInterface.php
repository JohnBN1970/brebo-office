<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

/** Read boundary for sender-specific mail signature data. */
interface MailSignatureReadRepositoryInterface {

  /**
   * @return array{name:string,role_labels:string[],company:string,email:string,phone:string,address:string}
   */
  public function signatureSource(int $communicationId): array;

}
