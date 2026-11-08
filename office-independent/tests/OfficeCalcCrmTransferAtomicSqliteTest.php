<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcCrmTransferService.php';

use Brebo\Office\Calculation\OfficeCalcCrmTransferService;

// Disposable SQLite fixture only. These are test inputs, never project budgets.
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "CRM_CALC_TRANSFER_SQLITE=SKIP_NO_DRIVER\n";
    exit(0);
}
final class TransferFixturePdo extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}
$db = new TransferFixturePdo('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys=ON');
foreach ([
    'CREATE TABLE office_projects (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL)',
    'CREATE TABLE accepted_calc (opportunity TEXT PRIMARY KEY, calculation INTEGER, revision TEXT, status TEXT)',
    'CREATE TABLE accepted_calc_lines (opportunity TEXT, line_id TEXT, cost_type TEXT, quantity INTEGER, unit_cost_cents INTEGER, PRIMARY KEY(opportunity,line_id))',
    'CREATE TABLE office_calc_crm_acceptance_transfers (id INTEGER PRIMARY KEY AUTOINCREMENT, crm_opportunity_id TEXT UNIQUE NOT NULL, acceptance_key TEXT UNIQUE NOT NULL, calculation_id INTEGER, accepted_revision TEXT, office_project_id INTEGER UNIQUE NOT NULL REFERENCES office_projects(id))',
    'CREATE TABLE office_calc_work_budget_baselines (id INTEGER PRIMARY KEY AUTOINCREMENT, acceptance_transfer_id INTEGER REFERENCES office_calc_crm_acceptance_transfers(id), version_number INTEGER NOT NULL, locked_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(acceptance_transfer_id,version_number))',
    'CREATE TABLE office_calc_work_budget_lines (id INTEGER PRIMARY KEY AUTOINCREMENT, baseline_id INTEGER NOT NULL REFERENCES office_calc_work_budget_baselines(id), source_line_id TEXT, cost_type TEXT, quantity INTEGER, unit_cost_cents INTEGER, budget_cents INTEGER, activity_ref TEXT, UNIQUE(baseline_id,source_line_id))',
] as $sql) $db->exec($sql);
$db->exec("INSERT INTO accepted_calc VALUES ('41',501,'r7','accepted'),('42',502,'r3','accepted'),('43',503,'r1','accepted'),('44',504,'r1','accepted')");
$db->exec("INSERT INTO accepted_calc_lines VALUES ('41','line-a','labor',2,100),('42','line-b','material',3,200),('43','line-c','other',1,300),('44','line-d','labor',1,400)");
$service = new OfficeCalcCrmTransferService($db);
$accept = static fn(string $id,int $calc,string $rev):array => ['crm_opportunity_id'=>$id,'calculation_id'=>$calc,'accepted_revision'=>$rev,'status'=>'accepted'];
$line = static fn(string $id,string $type,int $qty,int $cost):array => ['line_id'=>$id,'cost_type'=>$type,'quantity'=>$qty,'unit_cost_cents'=>$cost];
$verify = static function(PDO $pdo,array $acceptance,array $requestedLines):bool {
    $q=$pdo->prepare('SELECT calculation,revision,status FROM accepted_calc WHERE opportunity=?');
    $q->execute([$acceptance['crm_opportunity_id']]);
    $row=$q->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['status']!=='accepted'
        || (int)$row['calculation']!==$acceptance['calculation_id']
        || $row['revision']!==$acceptance['accepted_revision']) return false;
    $q=$pdo->prepare('SELECT line_id,cost_type,quantity,unit_cost_cents FROM accepted_calc_lines WHERE opportunity=? ORDER BY line_id');
    $q->execute([$acceptance['crm_opportunity_id']]);
    $stored=$q->fetchAll(PDO::FETCH_ASSOC);
    $expected=array_map(static fn(array $l):array=>[
        'line_id'=>$l['line_id'], 'cost_type'=>$l['cost_type'],
        'quantity'=>(int)$l['quantity'],'unit_cost_cents'=>(int)$l['unit_cost_cents'],
    ],$stored);
    usort($requestedLines,static fn(array $a,array $b):int=>strcmp($a['line_id'],$b['line_id']));
    return $expected===$requestedLines;
};
$create = static function(PDO $pdo,array $a):int {
    $q=$pdo->prepare('INSERT INTO office_projects(title) VALUES (?)');
    $q->execute(['Accepted CRM '.$a['crm_opportunity_id']]);
    return (int)$pdo->lastInsertId();
};
$count = static function(string $table)use($db):int { return (int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn(); };
$reject = static function(callable $run,string $label):void {
    try {$run();}catch(Throwable) {return;}
    throw new RuntimeException('Expected rejection: '.$label);
};
$ok=$service->transfer($accept('41',501,'r7'),[$line('line-a','labor',2,100)],$create,$verify);
if ($ok['project_id']!==1 || $count('office_projects')!==1 || $count('office_calc_work_budget_lines')!==1) {
    throw new RuntimeException('Successful transfer did not persist exactly one project/budget line');
}
$reject(static fn()=> $service->transfer($accept('41',501,'r7'),[$line('line-a','labor',2,100)],$create,$verify),'duplicate transfer');
$reject(static fn()=> $service->transfer($accept('41',501,'r8'),[$line('line-a','labor',2,100)],$create,$verify),'changed revision');
$reject(static fn()=> $service->transfer($accept('42',502,'r3'),[$line('line-b','material',3,201)],$create,$verify),'forged accepted amount');
$reject(static fn()=> $service->transfer($accept('42',502,'r4'),[$line('line-b','material',3,200)],$create,$verify),'unaccepted revision');
if ($count('office_projects')!==1) throw new RuntimeException('Rejected transfer created a project');
$reject(static fn()=> $service->transfer($accept('43',503,'r1'),[$line('line-c','other',1,300)],
    static function(PDO $pdo,array $a)use($create):int {
        $id=$create($pdo,$a);
        throw new RuntimeException('Simulated project adapter failure');
    },$verify),'project failure rollback');
if ($count('office_projects')!==1) throw new RuntimeException('Project adapter rollback failed');
$reject(static fn()=> $service->transfer($accept('44',504,'r1'),[$line('line-d','labor',1,400)],
    static function(PDO $pdo,array $a)use($create):int {
        $id=$create($pdo,$a);
        $pdo->exec("CREATE TRIGGER fail_line BEFORE INSERT ON office_calc_work_budget_lines BEGIN SELECT RAISE(ABORT,'line failure'); END");
        return $id;
    },$verify),'line insert rollback');
if ($count('office_projects')!==1 || $count('office_calc_crm_acceptance_transfers')!==1
    || $count('office_calc_work_budget_baselines')!==1 || $count('office_calc_work_budget_lines')!==1) {
    throw new RuntimeException('Atomic rollback failed after line persistence error');
}
echo "CRM_CALC_TRANSFER_ATOMIC_SQLITE=PASS\n";
