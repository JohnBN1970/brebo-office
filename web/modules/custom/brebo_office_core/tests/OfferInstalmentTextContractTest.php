<?php

declare(strict_types=1);

/**
 * Lightweight source contract for the commercial instalment offer flow.
 *
 * Run: php web/modules/custom/brebo_office_core/tests/OfferInstalmentTextContractTest.php
 */
$root = dirname(__DIR__);
$form = file_get_contents($root . '/src/Form/OfferVersionForm.php');
$preview = file_get_contents($root . '/src/Controller/OfferPreviewController.php');
if (!is_string($form) || !is_string($preview)) {
  throw new RuntimeException('Offertebronnen ontbreken.');
}
$requiredForm = [
  "commercialInstalmentScheduleSnapshot(\$node)",
  "formatInstalmentText(\$scheduleForText)",
  "'instalment_text'",
  "'commercial_instalment_text' => (string) \$form_state->getValue('instalment_text')",
  "'commercial_instalment_schedule' => \$commercialInstalmentSchedule",
  "return implode(\"\\n\", \$lines);",
];
foreach ($requiredForm as $needle) {
  if (!str_contains($form, $needle)) {
    throw new RuntimeException('Offerteformulier mist contract: ' . $needle);
  }
}
$requiredPreview = [
  "'field_brebo_offer_snapshot'",
  "['commercial_instalment_text']",
  "section('Betalingstermijnen', \$instalmentText)",
];
foreach ($requiredPreview as $needle) {
  if (!str_contains($preview, $needle)) {
    throw new RuntimeException('Offerteweergave mist contract: ' . $needle);
  }
}
echo "OfferInstalmentTextContractTest OK\n";
