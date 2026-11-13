<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeApprovedHoursSchema.php';
require_once dirname(__DIR__).'/src/Inzet/OfficeLabourShiftApprovalLinkSchema.php';
require_once dirname(__DIR__).'/src/Inzet/OfficeLabourBudgetDebitSchema.php';
require_once dirname(__DIR__).'/src/Inzet/OfficeLabourShiftAggregatePostingService.php';
use Brebo\Office\Inzet\OfficeApprovedHoursSchema;
use Brebo\Office\Inzet\OfficeLabourShiftApprovalLinkSchema;
use Brebo\Office\Inzet\OfficeLabourBudgetDebitSchema;
use Brebo\Office\Inzet\OfficeLabourShiftAggregatePostingService;
$dsn=getenv('OFFICE_INZET_TEST_DSN');
if (!$dsn || !str_starts_with($dsn,'mysql:') || !str_contains($dsn,'dbname=office_inzet_ci')) {
    throw new RuntimeException('Only disposable office_inzet_ci MySQL is permitted');
}
$db=new PDO($dsn,(string)getenv('OFFICE_INZET_TEST_USER'),(string)getenv('OFFICE_INZET_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
$db->exec('CREATE TABLE office_inzet_people (id BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$db->exec('CREATE TABLE office_inzet_shifts (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, project_id BIGINT UNSIGNED NOT NULL,person_id BIGINT UNSIGNED NOT NULL, starts_at DATETIME NOT NULL,ends_at DATETIME NOT NULL,status VARCHAR(32) NOT NULL) ENGINE=InnoDB');
foreach (array_merge(OfficeApprovedHoursSchema::statements(),OfficeLabourShiftApprovalLinkSchema::statements(),OfficeLabourBudgetDebitSchema::statements()) as $sql) $db->exec($sql);
$db->exec("INSERT INTO office_inzet_people VALUES (7)");
$db->exec("INSERT INTO office_inzet_shifts VALUES (11,23,7,'2026-11-13 08:00:00','2026-11-13 16:00:00','confirmed')");
$insert=$db->prepare("INSERT INTO office_inzet_approved_hours (project_id,person_id,work_date,approved_seconds,source_kind,source_reference,source_sha256,approved_by,approved_at,approval_reference) VALUES (23,7,'2026-11-13',?,'onsite_review',?,?,'reviewer','2026-11-13 17:00:00',?)");
$service=new OfficeLabourShiftAggregatePostingService($db);
foreach ([[7200,'first'],[14400,'second']] as [$seconds,$ref]) {
    $insert->execute([$seconds,$ref,hash('sha256',$ref),$ref]);
    $id=(int)$db->lastInsertId();
    $db->beginTransaction();
    $debitId=$service->postInsideTransaction($id);
    $db->commit();
    $db->beginTransaction();
    if ($service->postInsideTransaction($id)!==$debitId) throw new RuntimeException('Retry created a new debit');
    $db->commit();
}
$row=$db->query('SELECT COUNT(*) AS n,MIN(approved_seconds) AS actual,MIN(planned_seconds) AS planned,MIN(budget_debit_seconds) AS debit,MIN(variance_seconds) AS variance FROM office_inzet_labour_budget_debits')->fetch();
if (array_map('intval',array_values($row))!==[1,21600,28800,28800,-7200]) throw new RuntimeException('Incorrect shift aggregation '.json_encode($row));
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_labour_shift_approval_links')->fetchColumn()!==2) throw new RuntimeException('Missing approval evidence');
echo "MYSQL_AGGREGATE_TWO_APPROVALS_RETRY_OK\n";

/*
 * Real overlapping transactions on independent MySQL connections.
 * Start both writers at the same barrier; both approvals belong to shift 11.
 * A second budget debit is forbidden and the approved total must be 8h.
 */
if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
    throw new RuntimeException('pcntl required for concurrency integration');
}
foreach ([[3600,'concurrent-a'],[3600,'concurrent-b']] as [$seconds,$ref]) {
    $insert->execute([$seconds,$ref,hash('sha256',$ref),$ref]);
    $concurrentIds[]=(int)$db->lastInsertId();
}
$pair=stream_socket_pair(STREAM_PF_UNIX,STREAM_SOCK_STREAM,0);
if ($pair===false) throw new RuntimeException('Cannot create concurrency barrier');
$pid=pcntl_fork();
if ($pid===-1) throw new RuntimeException('Cannot fork MySQL writer');
if ($pid===0) {
    fclose($pair[0]);
    // Never reuse a parent PDO connection in a forked process.
    unset($service,$insert,$db);
    $childDb=new PDO($dsn,(string)getenv('OFFICE_INZET_TEST_USER'),(string)getenv('OFFICE_INZET_TEST_PASSWORD'),[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    ]);
    try {
        if (fread($pair[1],1)!=='G') throw new RuntimeException('Concurrency barrier failed');
        $childDb->beginTransaction();
        (new OfficeLabourShiftAggregatePostingService($childDb))->postInsideTransaction($concurrentIds[0]);
        $childDb->commit();
        exit(0);
    } catch (Throwable $error) {
        if ($childDb->inTransaction()) $childDb->rollBack();
        fwrite(STDERR,'CONCURRENT_CHILD_ERROR: '.$error->getMessage()."\n");
        exit(1);
    }
}
fclose($pair[1]);
// Forked children inherit the parent's MySQL socket. Replace the parent's
// connection before either process starts a transaction.
unset($service,$insert,$db);
$db=new PDO($dsn,(string)getenv('OFFICE_INZET_TEST_USER'),(string)getenv('OFFICE_INZET_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
]);
$service=new OfficeLabourShiftAggregatePostingService($db);
fwrite($pair[0],'G');
fclose($pair[0]);
$db->beginTransaction();
try {
    $service->postInsideTransaction($concurrentIds[1]);
    $db->commit();
} catch (Throwable $error) {
    try { if ($db->inTransaction()) $db->rollBack(); } catch (Throwable $ignored) {}
    pcntl_waitpid($pid,$status);
    throw $error;
}
pcntl_waitpid($pid,$status);
if (!pcntl_wifexited($status) || pcntl_wexitstatus($status)!==0) {
    throw new RuntimeException('Concurrent MySQL writer failed');
}
$row=$db->query('SELECT COUNT(*) AS n,MIN(approved_seconds) AS actual,MIN(planned_seconds) AS planned,MIN(budget_debit_seconds) AS debit,MIN(variance_seconds) AS variance FROM office_inzet_labour_budget_debits')->fetch();
if (array_map('intval',array_values($row))!==[1,28800,28800,28800,0]) {
    throw new RuntimeException('Concurrent shift aggregation incorrect '.json_encode($row));
}
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_labour_shift_approval_links')->fetchColumn()!==4) {
    throw new RuntimeException('Concurrent approval links missing');
}
echo "MYSQL_AGGREGATE_CONCURRENT_APPROVALS_OK\n";
