<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmLeadRouting.php';
use Brebo\Office\Crm\OfficeCrmLeadRouting as Routing;
$cases = [
    ['none', false, Routing::LEAD],
    ['prospect', false, Routing::LEAD],
    ['customer', false, Routing::OPPORTUNITY],
    ['customer', true, Routing::AWARDED],
    ['none', true, Routing::REVIEW],
    ['prospect', true, Routing::REVIEW],
    ['ambiguous', false, Routing::REVIEW],
    ['ambiguous', true, Routing::REVIEW],
];
foreach ($cases as [$match, $accepted, $expected]) {
    if (Routing::destination($match, $accepted) !== $expected) {
        throw new RuntimeException('Unexpected CRM routing for '.$match);
    }
    if (Routing::appearsInLeadBoard($expected) !== ($expected === Routing::LEAD)) {
        throw new RuntimeException('Converted lead still on active lead board.');
    }
}
echo "CRM lead routing contract OK\n";
