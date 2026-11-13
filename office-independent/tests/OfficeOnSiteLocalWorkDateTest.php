<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/src/Inzet/OfficeOnSiteApprovalService.php');
if (!is_string($source) || !str_contains($source, "\$in->setTimezone(new DateTimeZone('Europe/Brussels'))->format('Y-m-d')")) {
    throw new RuntimeException('OnSite work date must use same local timezone as planned shift resolver');
}
$utc=new DateTimeZone('UTC');
$local=new DateTimeZone('Europe/Brussels');
foreach ([
    ['2026-11-13 23:30:00','2026-11-14'],
    ['2026-07-13 22:30:00','2026-07-14'],
    ['2026-11-13 12:00:00','2026-11-13'],
] as [$timestamp,$expected]) {
    $date=(new DateTimeImmutable($timestamp,$utc))->setTimezone($local)->format('Y-m-d');
    if ($date!==$expected) throw new RuntimeException('OnSite local work date regression: '.$timestamp);
}
echo "ONSITE_LOCAL_WORK_DATE_OK\n";
