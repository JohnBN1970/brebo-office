<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

interface MailComposeSourceRepositoryInterface {

  /**
   * @return array{subject:string,transcript:string,html:string,from:string,to:string,cc:string,datetime:string}|null
   */
  public function load(int $communicationId): ?array;

}
