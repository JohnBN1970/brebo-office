<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeProjectIdentity.php';

use Brebo\Office\Project\OfficeProjectIdentity;

$valid = new OfficeProjectIdentity(42, 'BREBO-2026-042');
if ($valid->projectId !== 42 || $valid->projectCode !== 'BREBO-2026-042') {
  throw new RuntimeException('Canonical project identity changed.');
}
foreach ([0, -1] as $id) {
  try {
    new OfficeProjectIdentity($id, 'BREBO-2026-042');
    throw new RuntimeException('Invalid project ID accepted.');
  } catch (InvalidArgumentException $expected) {
  }
}
echo "Office project identity passed.\n";
