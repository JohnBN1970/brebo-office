<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Communication/OfficeInboxMessageClassifier.php';

use Brebo\Office\Communication\OfficeInboxMessageClassifier;

$classifier = new OfficeInboxMessageClassifier();
$result = $classifier->classify('Zoho verification code', 'noreply@example.test', 'Your code is 123456');
if ($result !== ['category' => 'verification_review', 'review_required' => true]) {
    throw new RuntimeException('Zoho verification must require manual review.');
}
$result = $classifier->classify('Nieuwe aanvraag', 'klant@example.test', 'Graag een offerte');
if ($result !== ['category' => 'unclassified', 'review_required' => true]) {
    throw new RuntimeException('Unknown mail must not auto-route.');
}
echo "OfficeInboxMessageClassifierTest PASS\n";
