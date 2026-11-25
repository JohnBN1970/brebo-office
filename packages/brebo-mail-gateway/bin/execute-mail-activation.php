<?php
declare(strict_types=1);

$transactionOpen = false;

try {
    if (posix_geteuid() !== 0 || $argc !== 2) {
        throw new RuntimeException('Ongeldige uitvoering.');
    }

    $stage = realpath($argv[1]);

    if (!$stage
        || !preg_match(
            '#^/var/lib/brebo-mail-activation/stage\.[a-zA-Z0-9]+$#D',
            $stage
        )
        || fileowner($stage) !== 0
        || (fileperms($stage) & 0777) !== 0700) {
        throw new RuntimeException('Ongeldige stagingomgeving.');
    }

    $activationWorkerDb = new PDO(
        'sqlite:/var/lib/brebo-mail-gateway/gateway.sqlite'
    );

    $activationWorkerDb->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $activationWorkerDb->exec('PRAGMA busy_timeout=5000');
    $activationWorkerDb->exec('PRAGMA synchronous=FULL');
    $activationWorkerDb->exec('BEGIN IMMEDIATE');

    $transactionOpen = true;

    putenv(
        'BREBO_MAIL_GATEWAY_DATA_DIR=/var/lib/brebo-mail-gateway'
    );

    putenv(
        'BREBO_MAIL_ACTIVATION_STAGE_DIR='.$stage
    );

    $render = '/usr/bin/php '.escapeshellarg(
        __DIR__.'/render-active-test-mailboxes.php'
    );

    passthru($render, $renderResult);

    if ($renderResult !== 0) {
        throw new RuntimeException('Configuratiegeneratie mislukt.');
    }

    echo "MAIL_ACTIVATION_LOCKED_PROJECTION=READY\n";

    $prepare = '/bin/bash '.escapeshellarg(
        __DIR__.'/prepare-activation-maildirs.sh'
    ).' '.escapeshellarg($stage);

    passthru($prepare, $prepareResult);

    if ($prepareResult !== 0) {
        throw new RuntimeException('Maildir-voorbereiding mislukt.');
    }


    $argv = [__FILE__, 'verify', $stage];
    $argc = count($argv);

    require __DIR__.'/finalize-mailbox-activation.php';

    echo "MAIL_ACTIVATION_TRANSACTION=LOCKED\n";

    $command = '/bin/bash '
        .escapeshellarg(
            __DIR__.'/publish-active-test-mailboxes.sh'
        ).' '.escapeshellarg($stage);

    passthru($command, $result);

    if ($result !== 0) {
        throw new RuntimeException('Publicatie mislukt.');
    }

    $argv = [__FILE__, 'commit', $stage];
    $argc = count($argv);

    require __DIR__.'/finalize-mailbox-activation.php';

    $activationWorkerDb->exec('COMMIT');
    $transactionOpen = false;

    echo "MAIL_ACTIVATION_TRANSACTION=COMMITTED\n";

} catch (Throwable $e) {
    if ($transactionOpen) {
        $activationWorkerDb->exec('ROLLBACK');
    }

    fwrite(STDERR,"MAIL_ACTIVATION_TRANSACTION=BLOCKED\n");
    exit(2);
}
