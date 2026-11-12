<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OfficeApprovedHoursBooking.php';

use Brebo\Office\Inzet\OfficeApprovedHoursBooking;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "APPROVED_HOURS_BOOKING_SKIPPED_SQLITE_MISSING\n";
    exit(0);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_project_memberships (project_id INTEGER, operator_id TEXT, permission TEXT)');
$db->exec('CREATE TABLE office_operator_accounts (id TEXT, active INTEGER)');
$db->exec('CREATE TABLE office_inzet_approved_hours (
 id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER, person_id INTEGER,
 work_date TEXT, approved_seconds INTEGER, source_kind TEXT, source_reference TEXT,
 source_sha256 TEXT, approved_by TEXT, approved_at TEXT, approval_reference TEXT,
 UNIQUE(source_kind,source_reference))');
$db->exec("INSERT INTO office_operator_accounts VALUES ('manager',1),('viewer',1)");
$db->exec("INSERT INTO office_project_memberships VALUES (42,'manager','manage'),(42,'viewer','read')");
$entry = [
 'project_id'=>42, 'person_id'=>5, 'work_date'=>'2026-11-12',
 'approved_seconds'=>3600, 'source_kind'=>'onsite_review',
 'source_reference'=>'presence:123', 'source_sha256'=>str_repeat('a',64),
 'approved_by'=>'manager', 'approved_at'=>'2026-11-12 09:00:00',
 'approval_reference'=>'decision:123',
];
$booking = new OfficeApprovedHoursBooking($db);
$reject = static function (array $value, string $operator) use ($booking): void {
    try { $booking->append($value,$operator); }
    catch (\DomainException|\InvalidArgumentException $e) { return; }
    throw new RuntimeException('Expected approved-hours rejection');
};
$reject($entry,'viewer');
$reject(array_replace($entry,['approved_by'=>'viewer']),'viewer');
$reject(array_replace($entry,['source_sha256'=>'invalid']),'manager');
$reject(array_replace($entry,['source_kind'=>'supplier_week_review']),'manager');
$reject(array_replace($entry,['work_date'=>'2026-02-30']),'manager');
$id = $booking->append($entry,'manager');
if ($id < 1 || (int)$db->query('SELECT COUNT(*) FROM office_inzet_approved_hours')->fetchColumn() !== 1) {
    throw new RuntimeException('Approved hours insert failed');
}
try {
    $booking->append($entry,'manager');
    throw new RuntimeException('Duplicate approval was booked');
} catch (PDOException $expected) {
    // Unique source constraint prevents duplicate bookings.
}
echo "APPROVED_HOURS_BOOKING_OK\n";
