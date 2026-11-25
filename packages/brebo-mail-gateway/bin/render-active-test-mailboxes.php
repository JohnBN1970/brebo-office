<?php
declare(strict_types=1);

umask(0077);

try {
    $data = getenv('BREBO_MAIL_GATEWAY_DATA_DIR');
    $out = getenv('BREBO_MAIL_ACTIVATION_STAGE_DIR');

    if (!$data || !$out || !is_dir($out)
        || is_link($out)
        || (fileperms($out) & 0777) !== 0700
        || fileowner($out) !== posix_geteuid()) {
        throw new RuntimeException('Ongeldige stagingomgeving.');
    }

    $db = new PDO('sqlite:'.$data.'/gateway.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA query_only=ON');
    $db->exec('PRAGMA busy_timeout=5000');

    $rows = $db->query(
        "SELECT a.address,a.revision,a.status,
         a.desired_active,a.applied_active,c.password_hash
         FROM mailbox_activation a
         JOIN mailbox m ON m.address=a.address
         LEFT JOIN mailbox_credential c ON c.address=a.address
         ORDER BY a.address"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        throw new RuntimeException('Activeringsregistratie ontbreekt.');
    }

    $maps = [];
    $users = [];
    $manifest = [];

    foreach ($rows as $row) {
        $address = $row['address'];

        if (!preg_match(
            '/^[a-z0-9][a-z0-9._+%-]*@testmail\.brebobv\.nl$/D',
            $address
        ) || !filter_var($address, FILTER_VALIDATE_EMAIL)
          || str_contains($address, '..')) {
            throw new RuntimeException('Onveilig mailboxadres.');
        }

        $active = $row['status'] === 'pending'
            ? (bool)$row['desired_active']
            : (bool)$row['applied_active'];

        if ($row['status'] === 'applying') {
            throw new RuntimeException('Verwerking reeds actief.');
        }

        $manifest[] = [
            'address'=>$address,
            'revision'=>(int)$row['revision'],
            'target_active'=>$active,
            'status'=>$row['status'],
        ];

        if (!$active) {
            continue;
        }

        $hash = (string)$row['password_hash'];

        if (!preg_match(
            '/^\{ARGON2ID\}\$argon2id\$v=19\$m=[0-9]+,t=[0-9]+,p=[0-9]+\$[A-Za-z0-9+\/]+\$[A-Za-z0-9+\/]+$/D',
            $hash
        )) {
            throw new RuntimeException('Ongeldige credential.');
        }

        $maps[] = $address.' 1';
        $users[] = $address.':'.$hash.'::::::';
    }

    $files = [
        'virtual_mailboxes'=>implode("\n",$maps).($maps ? "\n" : ''),
        'users'=>implode("\n",$users).($users ? "\n" : ''),
        'activation-manifest.json'=>json_encode(
            $manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
        ),
    ];

    foreach ($files as $name=>$content) {
        $path = $out.'/'.$name;
        $handle = fopen($path,'xb');

        if (!$handle) {
            throw new RuntimeException('Stagingbestand bestaat al.');
        }

        try {
            if (fwrite($handle,$content) !== strlen($content)
                || !fflush($handle)) {
                throw new RuntimeException('Schrijffout.');
            }
        } finally {
            fclose($handle);
        }

        chmod($path,0600);
    }

    echo "MAIL_ACTIVATION_PROJECTION=PASS\n";
    echo "TARGET_ACTIVE_MAILBOXES=".count($maps)."\n";

} catch (Throwable $e) {
    fwrite(STDERR,"MAIL_ACTIVATION_PROJECTION=BLOCKED\n");
    exit(2);
}
