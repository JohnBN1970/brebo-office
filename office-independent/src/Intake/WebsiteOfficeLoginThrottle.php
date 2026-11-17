<?php
declare(strict_types=1);
namespace Brebo\Office\Intake;
use PDO;
final class WebsiteOfficeLoginThrottle {
  public function __construct(private readonly PDO $db){}
  public function allowed(string $identity,int $now): bool {
    $q=$this->db->prepare('SELECT failures,window_started_at FROM office_login_attempts WHERE identity_hash=?');
    $q->execute([hash('sha256',$identity)]);$row=$q->fetch(PDO::FETCH_ASSOC);
    return !$row||strtotime($row['window_started_at'].' UTC')<=$now-900||(int)$row['failures']<5;
  }
  public function failed(string $identity,int $now): void {
    $hash=hash('sha256',$identity);$date=gmdate('Y-m-d H:i:s',$now-900);
    $q=$this->db->prepare('INSERT INTO office_login_attempts (identity_hash,failures,window_started_at) VALUES (?,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE failures=IF(window_started_at<=?,1,failures+1),window_started_at=IF(window_started_at<=?,UTC_TIMESTAMP(),window_started_at)');
    $q->execute([$hash,$date,$date]);
  }
  public function reset(string $identity): void {
    $q=$this->db->prepare('DELETE FROM office_login_attempts WHERE identity_hash=?');$q->execute([hash('sha256',$identity)]);
  }
}
