<?php
declare(strict_types=1);

try {
    if (posix_geteuid() !== 0 || $argc !== 3) {
        throw new RuntimeException('Ongeldige uitvoering.');
    }

    $mode = $argv[1];
    $stage = realpath($argv[2]);

    if (!in_array($mode, ['verify','commit','failed'], true)
        || !$stage
        || !preg_match(
            '#^/var/lib/brebo-mail-activation/stage\.[a-zA-Z0-9]+$#D',
            $stage
        )
        || fileowner($stage) !== 0
        || (fileperms($stage) & 0777) !== 0700) {
        throw new RuntimeException('Ongeldige stagingomgeving.');
    }

    $manifest = json_decode(
        file_get_contents($stage.'/activation-manifest.json'),
        true, 512, JSON_THROW_ON_ERROR
    );

    if (!is_array($manifest) || !$manifest) {
        throw new RuntimeException('Manifest ontbreekt.');
    }

    $sharedTransaction = isset($activationWorkerDb);

if (!$sharedTransaction) {
    fwrite(STDERR, "MAIL_ACTIVATION_TRANSACTION_REQUIRED\n");
    exit(2);
}


    $db = $sharedTransaction
        ? $activationWorkerDb
        : new PDO(
            'sqlite:/var/lib/brebo-mail-gateway/gateway.sqlite'
        );

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA busy_timeout=5000');
    if (!$sharedTransaction) {
        $db->exec('PRAGMA synchronous=FULL');
        $db->beginTransaction();
    }

    $read = $db->prepare(
        'SELECT revision,status,desired_active,applied_active
         FROM mailbox_activation WHERE address=?'
    );

    $update = $db->prepare(
        'UPDATE mailbox_activation
         SET applied_active=?,status=?,applied_at=?,error_code=?
         WHERE address=? AND revision=? AND status=?'
    );

    $count = 0;

    foreach ($manifest as $item) {
        $address = $item['address'];

        if (!filter_var($address, FILTER_VALIDATE_EMAIL)
            || !str_ends_with($address,'@testmail.brebobv.nl')) {
            throw new RuntimeException('Ongeldig adres.');
        }

        $read->execute([$address]);
        $row = $read->fetch(PDO::FETCH_ASSOC);

        if (!$row
            || (int)$row['revision'] !== $item['revision']
            || $row['status'] !== $item['status']) {
            throw new RuntimeException('Revisie gewijzigd.');
        }

        $target = $row['status'] === 'pending'
            ? (bool)$row['desired_active']
            : (bool)$row['applied_active'];

        if ($target !== $item['target_active']) {
            throw new RuntimeException('Status gewijzigd.');
        }

        if ($row['status'] !== 'pending') {
            continue;
        }

        $count++;

        if ($mode === 'verify') {
            continue;
        }

        $success = $mode === 'commit';

        $update->execute([
            $success ? (int)$target : (int)$row['applied_active'],
            $success ? ($target ? 'active' : 'inactive') : 'failed',
            $success ? time() : null,
            $success ? null : 'publication_failed',
            $address,
            $item['revision'],
            'pending',
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Statusupdate mislukt.');
        }
    }

    if ($count === 0) {
        throw new RuntimeException('Geen openstaande aanvragen.');
    }

    if ($mode === 'commit') {
        $publication = basename($stage);
        $hash = hash_file(
            'sha256',
            $stage.'/activation-manifest.json'
        );

        if ($hash === false) {
            throw new RuntimeException('Manifestcontrole mislukt.');
        }

        $journal = $db->prepare(
            'INSERT INTO mailbox_activation_publications
             (publication_id,manifest_sha256,mailbox_count,committed_at)
             VALUES (?,?,?,?)'
        );

        $journal->execute([
            $publication,
            $hash,
            $count,
            time()
        ]);
    }

    if (!$sharedTransaction) {
        $db->commit();
    }

    echo 'MAIL_ACTIVATION_'.strtoupper($mode).
         '=PASS count='.$count.PHP_EOL;

} catch (Throwable $e) {
    if (!empty($sharedTransaction)) {
        throw $e;
    }

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    fwrite(STDERR,"MAIL_ACTIVATION_FINALIZE=BLOCKED\n");
    exit(2);
}
