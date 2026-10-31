<?php
declare(strict_types=1);

/**
 * Offline native Zoho intake preflight for Office-test.
 * Does not connect to Zoho or a database, reveal credentials, write files,
 * change feature flags or advance IMAP cursors.
 *
 * Run with explicit environment:
 *   OFFICE_COMMUNICATION_VAULT_ROOT=/private/path
 *   OFFICE_COMMUNICATION_PUBLIC_ROOT=/public/path
 *   php office-independent/bin/office-communication-preflight.php
 */
$checks = [];
$check = static function (string $name, bool $pass, string $reason) use (&$checks): void {
    $checks[] = ['name'=>$name, 'pass'=>$pass, 'reason'=>$reason];
};
$check('PHP IMAP extension', extension_loaded('imap'), 'imap extension required');
$check('PDO MySQL driver', extension_loaded('pdo_mysql'), 'pdo_mysql extension required');
$check('mbstring extension', extension_loaded('mbstring'), 'mbstring extension required');
$check('SHA-256 support', in_array('sha256', hash_algos(), true), 'sha256 required');

$vault = getenv('OFFICE_COMMUNICATION_VAULT_ROOT');
$public = getenv('OFFICE_COMMUNICATION_PUBLIC_ROOT');
$vaultPath = is_string($vault) && $vault !== '' ? realpath($vault) : false;
$publicPath = is_string($public) && $public !== '' ? realpath($public) : false;
$check('Private vault configured', $vaultPath !== false && is_dir($vaultPath) && is_writable($vaultPath)
    && !is_link((string)$vault), 'set a writable existing private directory');
$check('Public root configured', $publicPath !== false && is_dir($publicPath), 'set the actual Office-test public webroot');
$separated = false;
if ($vaultPath !== false && $publicPath !== false) {
    $v = rtrim($vaultPath, DIRECTORY_SEPARATOR);
    $p = rtrim($publicPath, DIRECTORY_SEPARATOR);
    $separated = $v !== $p
        && !str_starts_with($v, $p . DIRECTORY_SEPARATOR)
        && !str_starts_with($p, $v . DIRECTORY_SEPARATOR);
}
$check('Vault isolated from public root', $separated, 'vault and public root must not contain each other');
$check('Vault source present', is_file(dirname(__DIR__) . '/src/Communication/OfficeAttachmentVault.php'),
    'attachment vault class missing');
$check('MIME extractor present', is_file(dirname(__DIR__) . '/src/Communication/OfficeImapMimeExtractor.php'),
    'MIME extractor class missing');
$check('Cursor migration present', is_file(dirname(__DIR__) . '/migrations/office_communication_cursors.sql'),
    'cursor migration missing');
$check('Attachment migration present', is_file(dirname(__DIR__) . '/migrations/office_communication_attachments.sql'),
    'attachment migration missing');

foreach ($checks as $item) {
    echo ($item['pass'] ? 'PASS' : 'FAIL') . ' ' . $item['name'];
    if (!$item['pass']) echo ' - ' . $item['reason'];
    echo PHP_EOL;
}
$failed = count(array_filter($checks, static fn(array $item): bool => !$item['pass']));
echo $failed === 0 ? "OFFLINE PREFLIGHT PASS; live Zoho/database acceptance still required.\n"
    : "OFFLINE PREFLIGHT BLOCKED; no import authorized.\n";
exit($failed === 0 ? 0 : 1);
