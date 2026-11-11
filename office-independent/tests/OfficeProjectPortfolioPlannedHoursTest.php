<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeProjectPortfolioPlannedHours.php';

use Brebo\Office\Project\OfficeProjectPortfolioPlannedHours;

$normal = OfficeProjectPortfolioPlannedHours::utcWeekBounds(2026, 46);
if ($normal !== ['start' => '2026-11-08 23:00:00', 'end' => '2026-11-15 23:00:00']) {
    throw new RuntimeException('Ordinary ISO week UTC bounds incorrect');
}
$spring = OfficeProjectPortfolioPlannedHours::utcWeekBounds(2026, 13);
if ((strtotime($spring['end'] . ' UTC') - strtotime($spring['start'] . ' UTC')) !== 167 * 3600) {
    throw new RuntimeException('Spring DST week must have 167 elapsed hours');
}
$autumn = OfficeProjectPortfolioPlannedHours::utcWeekBounds(2026, 43);
if ((strtotime($autumn['end'] . ' UTC') - strtotime($autumn['start'] . ' UTC')) !== 169 * 3600) {
    throw new RuntimeException('Autumn DST week must have 169 elapsed hours');
}
$rejectedInvalidWeek = false;
try {
    OfficeProjectPortfolioPlannedHours::utcWeekBounds(2027, 53);
} catch (InvalidArgumentException $expected) {
    $rejectedInvalidWeek = true;
}
if (!$rejectedInvalidWeek) {
    throw new RuntimeException('Nonexistent ISO week was accepted');
}
echo "PORTFOLIO_PLANNED_HOURS_OK\n";
