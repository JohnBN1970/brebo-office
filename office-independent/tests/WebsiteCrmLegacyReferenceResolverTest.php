<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmLegacyReferenceResolver;

require_once __DIR__ . '/../src/Intake/WebsiteCrmLegacyReferenceResolver.php';

$resolver = new WebsiteCrmLegacyReferenceResolver();
$references = [
  ['legacy_node_id' => 42, 'field' => 'field_brebo_opp_contact_ref', 'target_type' => 'brebo_contact', 'target_id' => 17],
  ['legacy_node_id' => 42, 'field' => 'field_custom', 'target_type' => 'unresolved', 'target_id' => 99],
];
$result = $resolver->resolve($references, ['brebo_contact' => [17 => 501], 'unresolved' => [99 => 999]]);
if (count($result['resolved']) !== 1 || count($result['unresolved']) !== 1
  || $result['resolved'][0]['office_target_id'] !== 501) {
  throw new RuntimeException('Approved mappings did not resolve safely.');
}
try {
  $resolver->assertReadyForCutover($result);
  throw new RuntimeException('Unresolved reference passed cutover gate.');
}
catch (RuntimeException $e) {
  if ($e->getMessage() === 'Unresolved reference passed cutover gate.') {
    throw $e;
  }
}
$complete = $resolver->resolve([$references[0]], ['brebo_contact' => [17 => 'office-contact-501']]);
$resolver->assertReadyForCutover($complete);
echo "Legacy CRM reference mapping checks passed.\n";
