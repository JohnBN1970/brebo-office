<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Communication/OfficeCommunicationRepository.php';

use Brebo\Office\Communication\OfficeCommunicationRepository;

$method = new ReflectionMethod(OfficeCommunicationRepository::class, 'storeMessage');
if ($method->getNumberOfRequiredParameters() !== 9) {
    throw new RuntimeException('Unexpected durable inbox contract.');
}
foreach (['recordProcessingFailure', 'markProcessed', 'pendingReview'] as $name) {
    if (!method_exists(OfficeCommunicationRepository::class, $name)) {
        throw new RuntimeException('Missing repository method: ' . $name);
    }
}
echo "Office communication repository contract OK\n";
