<?php
declare(strict_types=1);

try {
    $db = new PDO(
        'sqlite:/var/lib/brebo-mail-gateway/gateway.sqlite'
    );

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec('PRAGMA synchronous=FULL');

    $db->beginTransaction();

    $db->exec("CREATE TABLE IF NOT EXISTS mailbox_activation_publications (
        publication_id TEXT PRIMARY KEY,
        manifest_sha256 TEXT NOT NULL,
        mailbox_count INTEGER NOT NULL,
        committed_at INTEGER NOT NULL
    )");

    $db->commit();

    echo "MAIL_ACTIVATION_JOURNAL=READY\n";

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    fwrite(STDERR,"MAIL_ACTIVATION_JOURNAL=BLOCKED\n");
    exit(2);
}
