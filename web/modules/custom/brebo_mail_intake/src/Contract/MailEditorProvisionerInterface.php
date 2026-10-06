<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Contract;

interface MailEditorProvisionerInterface {
  public function ensure(): void;
}
