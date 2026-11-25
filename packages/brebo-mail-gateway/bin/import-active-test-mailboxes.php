<?php
declare(strict_types=1);

try {
    if (posix_geteuid() !== 0) {
        throw new RuntimeException('Root vereist.');
    }

    $map = '/etc/postfix/brebo_virtual_mailboxes';
    $users = '/etc/dovecot/brebo-auth/users';

    $valid = static fn(string $address): bool =>
        filter_var($address, FILTER_VALIDATE_EMAIL) !== false
        && preg_match(
            '/^[a-z0-9][a-z0-9._+%-]*@testmail\.brebobv\.nl$/D',
            $address
        ) === 1
        && !str_contains($address, '..');

    $active = [];
    $hashes = [];

    foreach (file($map, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $parts = preg_split('/\s+/', trim($line));

        if (count($parts) !== 2
            || !$valid($parts[0])
            || $parts[1] !== '1'
            || isset($active[$parts[0]])) {
            throw new RuntimeException('Ongeldige mailboxmap.');
        }

        $active[$parts[0]] = true;
    }

    foreach (file($users, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $parts = explode(':', $line);

        if (count($parts) !== 8
            || !$valid($parts[0])
            || isset($hashes[$parts[0]])
            || !str_starts_with($parts[1], '{ARGON2ID}$argon2id$')) {
            throw new RuntimeException('Ongeldige authenticatieconfiguratie.');
        }

        $hashes[$parts[0]] = $parts[1];
    }

    if (!$active
        || array_diff_key($active, $hashes)
        || array_diff_key($hashes, $active)) {
        throw new RuntimeException('Configuraties verschillen.');
    }

    $db = new PDO(
        'sqlite:/var/lib/brebo-mail-gateway/gateway.sqlite'
    );

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA query_only=ON');

    $rows = $db->query(
        'SELECT m.address,c.password_hash
         FROM mailbox m
         LEFT JOIN mailbox_credential c
         ON c.address=m.address'
    )->fetchAll(PDO::FETCH_ASSOC);

    $seen = [];
    $inactive = 0;

    foreach ($rows as $row) {
        $address = $row['address'];

        if (!$valid($address)) {
            throw new RuntimeException('Onverwacht mailboxadres.');
        }

        if (isset($active[$address])) {
            if (!hash_equals(
                $hashes[$address],
                (string)$row['password_hash']
            )) {
                throw new RuntimeException('Wachtwoordhash verschilt.');
            }

            $seen[$address] = true;
            echo "$address=ACTIVE\n";
        } else {
            $inactive++;
            echo "$address=INACTIVE\n";
        }
    }

    if (array_diff_key($active, $seen)) {
        throw new RuntimeException('Actieve mailbox ontbreekt in database.');
    }

    echo 'IMPORT_ACTIVE='.count($seen).PHP_EOL;
    echo 'IMPORT_INACTIVE='.$inactive.PHP_EOL;
    echo "MAIL_ACTIVATION_IMPORT_PREFLIGHT=PASS\n";

} catch (Throwable $e) {
    fwrite(STDERR, "MAIL_ACTIVATION_IMPORT_PREFLIGHT=BLOCKED\n");
    exit(2);
}
