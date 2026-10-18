<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetIdentityTargetVerifier.php';

use Brebo\Office\Inzet\OfficeInzetIdentityTargetVerifier;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY, active INTEGER NOT NULL)');
$db->exec('CREATE TABLE office_projects (id INTEGER PRIMARY KEY)');
$db->exec('INSERT INTO office_inzet_people VALUES (101,1),(102,0)');
$db->exec('INSERT INTO office_projects VALUES (17)');
$verifier = new OfficeInzetIdentityTargetVerifier($db);
$verifier->assertActivePersonAndProject(101, 17);
foreach ([[102,17], [103,17], [101,18], [0,17], [101,0]] as [$person,$project]) {
    try {
        $verifier->assertActivePersonAndProject($person,$project);
    } catch (RuntimeException $error) {
        continue;
    }
    throw new RuntimeException('Invalid Office target was accepted');
}
echo "Office Inzet identity target verification OK\n";
