<?php
declare(strict_types=1);

$src = dirname(__DIR__) . '/src';

foreach ([
    'Intake/OfficeMount.php',
    'Intake/WebsiteCrmOperatorContext.php',
    'Intake/WebsiteCrmOperatorSessionProviderInterface.php',
    'Intake/WebsiteOfficeCrmSessionRepository.php',
    'Intake/WebsiteOfficeCrmCookieSessionProvider.php',
    'Finance/OfficeFinanceAccess.php',
] as $file) {
    require_once $src . '/' . $file;
}

use Brebo\Office\Intake\OfficeMount;
use Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository;
use Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider;
use Brebo\Office\Finance\OfficeFinanceAccess;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("
CREATE TABLE office_operator_accounts (
    id TEXT PRIMARY KEY,
    active INTEGER NOT NULL,
    can_edit_crm INTEGER NOT NULL
);
CREATE TABLE office_operator_sessions (
    token_hash TEXT PRIMARY KEY,
    operator_id TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    revoked_at TEXT
);
CREATE TABLE office_module_permissions (
    operator_id TEXT NOT NULL,
    module TEXT NOT NULL,
    permission TEXT NOT NULL
);
");

$now = time();
$future = gmdate('Y-m-d H:i:s', $now + 3600);
$past = gmdate('Y-m-d H:i:s', $now - 3600);

$db->exec("
INSERT INTO office_operator_accounts VALUES
('manager',1,1),
('medewerker',1,1),
('verlopen',1,1),
('ingetrokken',1,1);

INSERT INTO office_module_permissions VALUES
('manager','finance','read');
");

$tokens = [
    'manager' => str_repeat('a', 40),
    'medewerker' => str_repeat('b', 40),
    'verlopen' => str_repeat('c', 40),
    'ingetrokken' => str_repeat('d', 40),
];

$insert = $db->prepare("
INSERT INTO office_operator_sessions
(token_hash,operator_id,expires_at,revoked_at)
VALUES (?,?,?,?)
");

foreach ($tokens as $id => $token) {
    $insert->execute([
        hash('sha256', $token),
        $id,
        $id === 'verlopen' ? $past : $future,
        $id === 'ingetrokken' ? $past : null,
    ]);
}

function financeAllowed(PDO $db, string $token, int $now): bool
{
    $provider = new WebsiteOfficeCrmCookieSessionProvider(
        new WebsiteOfficeCrmSessionRepository($db),
        [OfficeMount::cookieName('session') => $token],
        $now
    );

    $operator = $provider->currentOperator();

    return $operator !== null
        && (new OfficeFinanceAccess($db, $operator->operatorId))
            ->allows('read');
}

$expected = [
    'manager' => true,
    'medewerker' => false,
    'verlopen' => false,
    'ingetrokken' => false,
];

foreach ($expected as $id => $allowed) {
    $actual = financeAllowed($db, $tokens[$id], $now);

    if ($actual !== $allowed) {
        throw new RuntimeException("Finance sessietest mislukt: $id");
    }

    echo "OK: $id - " .
        ($actual ? 'Finance toegestaan' : 'Finance geweigerd') .
        PHP_EOL;
}

echo "OfficeFinanceSessionIntegrationTest OK\n";
