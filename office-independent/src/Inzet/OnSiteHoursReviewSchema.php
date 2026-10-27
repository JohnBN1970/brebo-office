<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
/** SQL proposal renderer only. It never opens a connection or executes SQL. */
final class OnSiteHoursReviewSchema {
  public static function reviewSql(string $eventIdCollation):string {
    if(!preg_match('/^utf8mb4_[a-z0-9_]+$/D',$eventIdCollation))throw new \InvalidArgumentException('Reviewed event-id collation required');
    $sql=file_get_contents(dirname(__DIR__,2).'/migrations/proposed-onsite-hours-reviews.sql');
    if(!is_string($sql))throw new \RuntimeException('Review SQL template unavailable');
    return preg_replace('/\b(in_event_id|out_event_id) VARCHAR\(100\)/', '$1 VARCHAR(100) CHARACTER SET utf8mb4 COLLATE '.$eventIdCollation,$sql);
  }
}
