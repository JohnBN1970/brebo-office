<?php
declare(strict_types=1);

$code = file_get_contents(dirname(__DIR__) . '/src/Intake/OfficeIntakeSecureReceiver.php');
if (!is_string($code)) {
    throw new RuntimeException('receiver_missing');
}
$positions = [];
foreach ([
    'strlen($rawBody) > 8192',
    '$this->authenticator->verify',
    'json_decode($rawBody',
    "($envelope['source'] ?? null) !== $source",
    'OfficeCanonicalIntakeAdapter::map',
    '$this->replays->reserve',
    '$this->handoff->register',
] as $check) {
    $pos = strpos($code, $check);
    if ($pos === false) {
        throw new RuntimeException('receiver_contract_missing_' . $check);
    }
    $positions[] = $pos;
}
if ($positions !== array_values(array_unique($positions)) || $positions !== (function (array $values): array { $sorted = $values; sort($sorted, SORT_NUMERIC); return $sorted; })($positions)) {
    throw new RuntimeException('receiver_order_invalid');
}
echo "OfficeIntakeSecureReceiverContractTest OK\n";
