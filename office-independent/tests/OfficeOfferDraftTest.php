<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Offer/OfficeOfferDraft.php';

use Brebo\Office\Offer\OfficeOfferDraft;

function expectInvalid(callable $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Expected invalid offer draft');
}

$standalone = new OfficeOfferDraft(1, 'Advieswerk');
if ($standalone->projectId !== null || $standalone->calculationId !== null || $standalone->isProjectChange()) {
    throw new RuntimeException('Standalone offer must not require project or calculation');
}

$calculated = new OfficeOfferDraft(1, 'Renovatie', calculationId: 42, calculationVersion: 'v3');
if ($calculated->calculationVersion !== 'v3') {
    throw new RuntimeException('Calculation version must be retained');
}

$more = new OfficeOfferDraft(1, 'Aanvullende werkzaamheden', projectId: 7, changeOrderId: 8, changeType: 'more');
$less = new OfficeOfferDraft(1, 'Vervallen werkzaamheden', projectId: 7, changeOrderId: 9, changeType: 'less');
if (!$more->isProjectChange() || !$less->isProjectChange()) {
    throw new RuntimeException('Project changes must be recognized');
}

expectInvalid(fn () => new OfficeOfferDraft(1, 'Meerwerk', changeOrderId: 8, changeType: 'more'));
expectInvalid(fn () => new OfficeOfferDraft(1, 'Meerwerk', projectId: 7, changeOrderId: 8));
expectInvalid(fn () => new OfficeOfferDraft(1, 'Meerwerk', projectId: 7, changeType: 'more'));
expectInvalid(fn () => new OfficeOfferDraft(1, 'Offerte', calculationId: 42));
expectInvalid(fn () => new OfficeOfferDraft(1, 'Offerte', calculationVersion: 'v1'));
expectInvalid(fn () => new OfficeOfferDraft(1, 'Offerte', projectId: -1));
expectInvalid(fn () => new OfficeOfferDraft(1, 'Offerte', projectId: 7, changeOrderId: 8, changeType: 'invalid'));

echo "OfficeOfferDraftTest: PASS\n";
