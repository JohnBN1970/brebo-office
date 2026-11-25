<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_TEST_MAIL_ACTIVATION_ENABLED') !== 'enabled') {
    fwrite(STDERR, "Activation sync disabled.\n"); exit(2);
}
try {
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '', getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'brebo_office_test') throw new RuntimeException('Wrong database');
    $core = dirname(__DIR__).'/packages/brebo-mail-core/src/';
    if (!is_dir($core)) $core = dirname(__DIR__,2).'/packages/brebo-mail-core/src/';
    foreach (['Contract/GatewayHttpClientInterface.php','Domain/GatewayRequestSignature.php'] as $file) require_once $core.$file;
    require_once dirname(__DIR__).'/src/Mail/OfficeGatewayHttpClient.php';
    $client = new \Brebo\Office\Mail\OfficeGatewayHttpClient();
    $lock = fopen(sys_get_temp_dir().'/brebo-office-mail-activation-sync.lock','c');
    if ($lock === false) throw new RuntimeException('Lock unavailable');
    if (!flock($lock, LOCK_EX | LOCK_NB)) exit(0);
    $rows = $db->query("SELECT id,address,gateway_ref,active FROM office_mail_mailboxes WHERE gateway_ref IS NOT NULL AND address LIKE '%@testmail.brebobv.nl' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $updated=0; $failures=0;
    foreach ($rows as $row) {
        try {
            if (!filter_var($row['address'], FILTER_VALIDATE_EMAIL) || !str_ends_with($row['address'],'@testmail.brebobv.nl') || empty($row['gateway_ref'])) continue;
            $state=$client->post('/v1/mailboxes/activation-status',['address'=>$row['address'],'reference'=>$row['gateway_ref']]);
            if (($state['address']??null)!==$row['address'] || ($state['reference']??null)!==$row['gateway_ref']) throw new RuntimeException('Identity mismatch');
            if (($state['status']??null)!==(($state['applied_active']??null)===true?'active':'inactive') || !is_bool($state['applied_active']??null) || ($state['desired_active']??null)!==$state['applied_active']) continue;
            $active=(int)$state['applied_active'];
            if ($active===(int)$row['active']) continue;
            $stmt=$db->prepare('UPDATE office_mail_mailboxes SET active=? WHERE id=? AND address=? AND gateway_ref=? AND active=?');
            $stmt->execute([$active,$row['id'],$row['address'],$row['gateway_ref'],(int)$row['active']]);
            $updated+=$stmt->rowCount();
        } catch (Throwable $e) {
            ++$failures;
            error_log('Activation sync failed mailbox='.(int)$row['id'].' type='.$e::class);
        }
    }
    flock($lock, LOCK_UN); fclose($lock);
    echo "ACTIVATION_SYNC_UPDATED=".$updated."\nACTIVATION_SYNC_FAILURES=".$failures."\n";
    exit($failures ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "ACTIVATION_SYNC_BLOCKED ".$e::class."\n"); exit(2);
}
