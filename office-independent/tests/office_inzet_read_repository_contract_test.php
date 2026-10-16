<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeNativeProjectAccess.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetReadRepository.php';

use Brebo\Office\Inzet\OfficeInzetReadRepository;

$db = new PDO('sqlite::memory:');
$repository = new OfficeInzetReadRepository($db);
if ($repository->teamForProject('unknown', 1) !== []) throw new RuntimeException('Team leaked without membership.');
if ($repository->shiftsForProject('unknown', 1) !== []) throw new RuntimeException('Shifts leaked without membership.');
echo "Office Inzet read repository fail-closed contract OK\n";
