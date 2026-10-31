<?php
declare(strict_types=1);

use Brebo\Office\Communication as Mail;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

try {
    $password = getenv('IMAP_PASS');

    if (!is_string($password) || $password === '') {
        throw new RuntimeException('Zoho app-wachtwoord ontbreekt');
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

    $result = $sync->syncDurable(2, 5);

    echo "=== SYNCHRONISATIE ===\n";
    echo "Verwerkt: " . count($result['ids']) . "\n";
    echo "Laatste UID: " . $result['last_uid'] . "\n";
    echo "UIDVALIDITY: " . $result['uidvalidity'] . "\n";
    echo "=== GEREED ===\n";

} catch (Throwable $e) {
    fwrite(STDERR, "STOP: " . $e->getMessage() . "\n");
    exit(1);
}
