<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Mail/OfficeMailRoutingSuggestions.php';
use Brebo\Office\Mail\OfficeMailRoutingSuggestions as S;
$cases = [
  ['Factuur materialen', '', 'finance'],
  ['Offerteaanvraag', '', 'crm'],
  ['Inspectierapport', '', 'inspecties'],
  ['Factuur en offerteaanvraag', '', 'nieuw'],
  ['Goedemorgen', 'Kunnen we even bellen?', 'nieuw'],
];
foreach ($cases as [$subject,$body,$expected]) {
  $actual=S::suggest($subject,$body)['module'];
  if ($actual !== $expected) throw new RuntimeException("$subject: expected $expected, got $actual");
}
echo "mail-routing-suggestions=OK\n";
