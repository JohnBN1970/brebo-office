<?php

declare(strict_types=1);

namespace Brebo\Mail\Domain;

enum ProvisioningStatus: string {
  case Pending = 'pending';
  case Provisioning = 'provisioning';
  case Active = 'active';
  case Error = 'error';
}
