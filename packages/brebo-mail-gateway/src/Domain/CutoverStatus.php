<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Domain;

enum CutoverStatus: string {
  case Draft = 'draft';
  case Validated = 'validated';
  case Ready = 'ready';
  case Active = 'active';
  case RolledBack = 'rolled_back';
}
