<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressProposal.php';
use Brebo\Office\Buildings\OfficeResidentAddressProposal as Proposal;
function assertProposal(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}
$input = [
  'street' => 'Voorbeeldstraat', 'house_number' => '12', 'addition' => 'A',
  'postal_code' => '1234 ab', 'city' => 'Amsterdam',
  'resident_name' => 'Test Bewoner', 'resident_email' => 'test@example.invalid',
];
$a = Proposal::fromRecord($input, 'xlsx', 'sheet-1:row-2');
$b = Proposal::fromRecord($input, 'email', 'message-17');
assertProposal($a['status'] === 'proposal', 'complete address should be a proposal');
assertProposal($a['match_key'] === $b['match_key'], 'same address must have stable identity');
assertProposal($a['address']['postcode'] === '1234AB', 'postcode normalization failed');
assertProposal($a['resident']['name'] === 'Test Bewoner', 'resident missing');
assertProposal($a['resident_write_allowed'] === false, 'resident must not auto-write');
assertProposal($a['source']['id'] === 'sheet-1:row-2', 'source provenance missing');
$incomplete = Proposal::fromRecord(['street' => 'Voorbeeldstraat'], 'pdf', 'document-9');
assertProposal($incomplete['status'] === 'needs_review', 'incomplete address must require review');
$rejected = false;
try { Proposal::fromRecord(['street' => 'X', 'house_number' => '1', 'postal_code' => '1234AB', 'bag_id' => 'invalid'], 'email', 'msg'); }
catch (InvalidArgumentException $e) { $rejected = true; }
assertProposal($rejected, 'invalid BAG ID accepted');
echo "OFFICE_RESIDENT_ADDRESS_PROPOSAL_OK\n";
