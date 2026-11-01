<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficePersonnelProfileForm.php';

use Brebo\Office\Inzet\OfficePersonnelProfileForm;

$html = OfficePersonnelProfileForm::render(17, 'Test <Medewerker>');
foreach (['Medewerkersdossier', 'phone_country', 'phone_number', 'nationality', 'preferred_language', 'skills[]', 'vca_expiry', 'fieldset disabled', '<svg', 'value="NL"', 'value="BE"', 'Personeelsnummer', 'Polski', 'Română', 'Türkçe'] as $required) {
  if (!str_contains($html, $required)) throw new RuntimeException('Missing profile field: ' . $required);
}
if (str_contains($html, '<Medewerker>') || !str_contains($html, '&lt;Medewerker&gt;')) {
  throw new RuntimeException('Employee name must be escaped');
}
if (str_contains($html, '<form') || str_contains($html, 'type="submit"')) {
  throw new RuntimeException('Unverified personnel profile must remain non-writing');
}
try {
  OfficePersonnelProfileForm::render(0, 'Invalid');
  throw new RuntimeException('Missing identity check');
} catch (InvalidArgumentException $expected) {}
$projects = file_get_contents(dirname(__DIR__) . '/public/office-projects.php');
if (!is_string($projects) || !str_contains($projects, 'OfficePersonnelProfileForm::render')) {
  throw new RuntimeException('Personnel profile is not linked to the manager-only screen');
}
echo "Personnel profile form contract: PASS\n";
