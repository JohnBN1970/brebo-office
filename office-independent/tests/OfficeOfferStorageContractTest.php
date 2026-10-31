<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeOfferSchema.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferDraft.php';
require_once __DIR__ . '/../src/Offer/OfficeOfferRepository.php';

use Brebo\Office\Offer\OfficeOfferSchema;
use Brebo\Office\Offer\OfficeOfferDraft;
use Brebo\Office\Offer\OfficeOfferRepository;

$statements = OfficeOfferSchema::statements();
if (count($statements) !== 2
    || !str_contains($statements[0], 'office_project_id BIGINT UNSIGNED NULL')
    || !str_contains($statements[0], 'calculation_id BIGINT UNSIGNED NULL')
    || !str_contains($statements[1], 'UNIQUE KEY uq_offer_version')) {
    throw new RuntimeException('Offer storage schema contract invalid');
}
$repo = file_get_contents(__DIR__ . '/../src/Offer/OfficeOfferRepository.php');
if (!is_string($repo)
    || !str_contains($repo, 'createDraft(OfficeOfferDraft $draft)')
    || !str_contains($repo, 'forProject(int $projectId)')
    || !str_contains($repo, 'forRelation(int $relationId)')
    || str_contains($repo, 'office_project_terms')
    || str_contains($repo, 'office_project_change_orders')) {
    throw new RuntimeException('Offer storage boundary invalid');
}
$draft = new OfficeOfferDraft(1, 'Zelfstandige offerte');
if ($draft->projectId !== null || $draft->calculationId !== null) {
    throw new RuntimeException('Standalone draft requires no project or calculation');
}
echo "OfficeOfferStorageContractTest: PASS\n";
