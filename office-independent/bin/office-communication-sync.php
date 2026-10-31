<?php
declare(strict_types=1);

use Brebo\Office\Communication as Mail;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

try {
    $lock = fopen('/home/u213420663/office-test-mail-config/sync.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Sync lock unavailable');
    }

    $secretPath = '/home/u213420663/office-test-mail-config/zoho-app-password';
    if (!is_file($secretPath) || is_link($secretPath) || (fileperms($secretPath) & 0077) !== 0) {
        throw new RuntimeException('Private secret unavailable or unsafe');
    }
    $password = file_get_contents($secretPath);
    if (!is_string($password) || $password === '') {
        throw new RuntimeException('Zoho app-wachtwoord ontbreekt');
    }

    $limit = filter_var(getenv('OFFICE_MAIL_BATCH_LIMIT') ?: '5', FILTER_VALIDATE_INT);
    if ($limit === false || $limit < 1 || $limit > 100) {
        throw new RuntimeException('Batch limit must be 1..100');
    }

    $config = require '/home/u213420663/domains/brebobv.nl/office_calc_test/config/config.php';

    $db = new PDO(
        $config['OFFICE_INTAKE_PDO_DSN'],
        $config['OFFICE_INTAKE_DB_USER'],
        $config['OFFICE_INTAKE_DB_PASSWORD'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
        throw new RuntimeException('Verkeerde database');
    }

    $stmt = $db->prepare(
        'SELECT mailbox_key FROM office_communication_mailboxes WHERE id = ?'
    );
    $stmt->execute([2]);

    if ($stmt->fetchColumn() !== 'john.boon@brebobv.nl') {
        throw new RuntimeException('Verkeerde mailbox');
    }

    $_SERVER['DOCUMENT_ROOT'] =
        '/home/u213420663/domains/brebobv.nl/public_html';

    $root = dirname(__DIR__) . '/src/Communication/';

    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'Brebo\\Office\\Communication\\';

        if (str_starts_with($class, $prefix)) {
            $file = $root . substr($class, strlen($prefix)) . '.php';

            if (is_file($file)) {
                require_once $file;
            }
        }
    });

    $source = new Mail\OfficeImapReadOnlySource(
        'imappro.zoho.eu',
        993,
        'john.boon@brebobv.nl',
        $password,
        'INBOX',
        new Mail\OfficeImapMimeExtractor()
    );

    $importer = new Mail\OfficeInboxImportBatch(
        new Mail\OfficeLegacyMailIntakeBridge(),
        new Mail\OfficeCommunicationRepository($db),
        new Mail\OfficeAttachmentImportService(
            new Mail\OfficeAttachmentVault(
                '/home/u213420663/office-test-mail-vault'
            ),
            new Mail\OfficeAttachmentRecordRepository($db)
        )
    );

    $sync = new Mail\OfficeInboxSyncService(
        $source,
        $importer,
        new Mail\OfficeImapCursorRepository($db)
    );

    $result = $sync->syncDurable(2, $limit);

    echo "=== SYNCHRONISATIE ===\n";
    echo "Verwerkt: " . count($result['ids']) . "\n";
    echo "Laatste UID: " . $result['last_uid'] . "\n";
    echo "UIDVALIDITY: " . $result['uidvalidity'] . "\n";
    echo "=== GEREED ===\n";
    error_log(gmdate(DATE_ATOM) . " OK count=" . count($result['ids']) . " uid=" . $result['last_uid'] . "\n", 3, '/home/u213420663/office-test-mail-logs/sync.log');

} catch (Throwable $e) {
    fwrite(STDERR, "STOP: synchronisatie mislukt; controleer het beveiligde log\n");
    error_log(gmdate(DATE_ATOM) . " FAIL " . get_class($e) . "\n", 3, '/home/u213420663/office-test-mail-logs/sync.log');
    exit(1);
}
