<?php
declare(strict_types=1);
use Brebo\Office\Finance\OfficeSupplierRecipientPolicy as Policy;
require_once __DIR__ . '/../src/Finance/OfficeSupplierRecipientPolicy.php';

function check(bool $value, string $message): void {
    if (!$value) throw new RuntimeException($message);
}
function blocked(array $data): bool {
    try { Policy::verifiedSnapshot($data, 17, 2000); return false; }
    catch (RuntimeException $e) { return true; }
}
$account = [
    'relation_id' => 7, 'iban' => 'NL91 ABNA 0417 1643 00',
    'account_holder' => 'Voorbeeld Leverancier BV', 'status' => 'active',
    'verification_status' => 'verified', 'verified_at' => 1000, 'verified_by' => 8,
    'verification_evidence_id' => 'evidence-1', 'changed_at' => 900,
];
check(Policy::validIban($account['iban']), 'Valid IBAN rejected');
check(!Policy::validIban('NL00ABNA0417164300'), 'Invalid IBAN accepted');
check(!Policy::validIban('NL91ABNA0417-164300'), 'Punctuated IBAN accepted');
check(!Policy::validIban('NL91ABNA0417164300!'), 'Malformed IBAN accepted');
$first = Policy::verifiedSnapshot($account, 17, 2000);
check($first['iban'] === 'NL91ABNA0417164300', 'IBAN not normalized');
check(!blocked($account), 'Verified recipient blocked');
check(blocked(array_diff_key($account, ['verification_evidence_id'=>true])), 'Missing evidence accepted');
check(blocked(array_replace($account, ['verification_status'=>'pending'])), 'Unverified account accepted');
check(blocked(array_replace($account, ['changed_at'=>1001])), 'Changed account accepted');
check(blocked(array_replace($account, ['account_holder'=>''])), 'Missing holder accepted');
check(blocked(array_replace($account, ['status'=>'inactive'])), 'Inactive supplier account accepted');
check(blocked(array_replace($account, ['iban'=>'NL00ABNA0417164300'])), 'Invalid account accepted');
$second = Policy::verifiedSnapshot(array_replace($account, ['account_holder'=>'Andere rekeninghouder']), 17, 2000);
check($first['recipient_hash'] !== $second['recipient_hash'], 'Changed account holder did not invalidate snapshot');
check(!Policy::canRelease(false, true, true, 3, 4, false), 'Unauthorized release accepted');
check(!Policy::canRelease(true, true, false, 3, 4, false), 'Changed recipient accepted');
check(!Policy::canRelease(true, false, true, 3, 4, false), 'Red controller accepted');
check(!Policy::canRelease(true, true, true, 3, 3, true), 'Four-eyes violation accepted');
check(Policy::canRelease(true, true, true, 3, 3, false), 'Optional four-eyes rejected');
check(Policy::canRelease(true, true, true, 3, 4, true), 'Valid second approver rejected');
check(blocked(array_replace($account, ['verified_at'=>2001])), 'Future verification accepted');
check(blocked(array_replace($account, ['verified_by'=>0])), 'Missing verifier accepted');
check(blocked(array_replace($account, ['relation_id'=>0])), 'Missing supplier accepted');
check(blocked(array_replace($account, ['verification_evidence_id'=>''])), 'Empty evidence accepted');
check(blocked(array_replace($account, ['changed_at'=>1000 + 1])), 'Post-verification change accepted');
$changedEvidence = Policy::verifiedSnapshot(array_replace($account, ['verification_evidence_id'=>'evidence-2']), 17, 2000);
check($first['recipient_hash'] !== $changedEvidence['recipient_hash'], 'Changed verification evidence did not invalidate snapshot');
$changedVerifier = Policy::verifiedSnapshot(array_replace($account, ['verified_by'=>9]), 17, 2000);
check($first['recipient_hash'] !== $changedVerifier['recipient_hash'], 'Changed verifier did not invalidate snapshot');
check(!Policy::canRelease(true, true, true, 0, 4, false), 'Missing preparer accepted');
check(!Policy::canRelease(true, true, true, 3, 0, false), 'Missing releaser accepted');
echo "OfficeSupplierRecipientPolicy regression checks passed\n";
