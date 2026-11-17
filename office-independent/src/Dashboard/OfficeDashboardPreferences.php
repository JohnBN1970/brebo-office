<?php
declare(strict_types=1);
namespace Brebo\Office\Dashboard;

use PDO;
use RuntimeException;

/**
 * Stores presentation preferences keyed by the authenticated Office operator.
 * No role grants or business data are persisted here.
 */
final class OfficeDashboardPreferences {
  public function __construct(private PDO $db) {}

  public static function schema(): string {
    return 'CREATE TABLE IF NOT EXISTS office_dashboard_preferences ('
      .'operator_id BIGINT UNSIGNED NOT NULL PRIMARY KEY, '
      .'layout_json TEXT NOT NULL, '
      .'updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
      .') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
  }

  /** @return list<string> */
  public function load(string $authenticatedOperatorId, array $permitted): array {
    if (!ctype_digit($authenticatedOperatorId) || (int)$authenticatedOperatorId <= 0) throw new RuntimeException('Authenticated operator required');
    $statement = $this->db->prepare('SELECT layout_json FROM office_dashboard_preferences WHERE operator_id = :id');
    $statement->execute([':id' => $authenticatedOperatorId]);
    $json = $statement->fetchColumn();
    if (!is_string($json)) return OfficePersonalDashboard::layout([], $permitted);
    $decoded = json_decode($json, true);
    return OfficePersonalDashboard::layout(is_array($decoded) && array_is_list($decoded) ? $decoded : [], $permitted);
  }

  /** @return list<string> */
  public function save(string $authenticatedOperatorId, array $requested, array $permitted): array {
    if (!ctype_digit($authenticatedOperatorId) || (int)$authenticatedOperatorId <= 0) throw new RuntimeException('Authenticated operator required');
    $layout = OfficePersonalDashboard::layout($requested, $permitted);
    $statement = $this->db->prepare(
      'INSERT INTO office_dashboard_preferences (operator_id, layout_json) VALUES (:id, :layout) '
      .'ON DUPLICATE KEY UPDATE layout_json = VALUES(layout_json)'
    );
    $statement->execute([
      ':id' => $authenticatedOperatorId,
      ':layout' => json_encode($layout, JSON_THROW_ON_ERROR),
    ]);
    return $layout;
  }

  public static function normalizeBoards(array $input,array $permitted): array {
    $boards=[];
    foreach(array_slice($input,0,10) as $id=>$board){
      if(!is_string($id)||!preg_match('/^[a-z0-9_-]{1,32}$/D',$id)||!is_array($board))continue;
      $name=is_string($board['name']??null)?trim($board['name']):'Dashboard';
      $columns=filter_var($board['columns']??3,FILTER_VALIDATE_INT);
      $boards[$id]=['name'=>mb_substr($name,0,60),'columns'=>in_array($columns,[1,2,3],true)?$columns:3,
        'widgets'=>OfficePersonalDashboard::layout(is_array($board['widgets']??null)?$board['widgets']:[],$permitted)];
    }
    return $boards;
  }
  public function boards(string $operator,array $permitted): array {
    if(!ctype_digit($operator)||(int)$operator<1)throw new RuntimeException('Authenticated operator required');
    $q=$this->db->prepare('SELECT layout_json FROM office_dashboard_preferences WHERE operator_id=?');$q->execute([$operator]);$raw=$q->fetchColumn();
    $data=is_string($raw)?json_decode($raw,true):[];
    $boards=self::normalizeBoards(is_array($data['boards']??null)?$data['boards']:['main'=>['name'=>'Mijn dashboard','widgets'=>is_array($data)&&array_is_list($data)?$data:[]]],$permitted);
    return ['boards'=>$boards,'revision'=>hash('sha256',is_string($raw)?$raw:'')];
  }
  /** Conditional update prevents two browser tabs from silently overwriting one another. */
  public function saveBoard(string $operator,string $id,array $board,array $permitted,string $revision): void {
    $normalized=self::normalizeBoards([$id=>$board],$permitted);
    if(!isset($normalized[$id]))throw new \InvalidArgumentException('Invalid dashboard');
    $current=$this->boards($operator,$permitted);
    if(!hash_equals($current['revision'],$revision))throw new \DomainException('Dashboard gewijzigd; laad opnieuw.');
    if(!isset($current['boards'][$id])&&count($current['boards'])>=10)throw new \InvalidArgumentException('Maximaal tien dashboards.');
    $current['boards'][$id]=$normalized[$id];
    $json=json_encode(['version'=>2,'boards'=>$current['boards']],JSON_THROW_ON_ERROR);
    if(hash_equals($revision,hash('sha256',$json)))return;
    $q=$this->db->prepare('UPDATE office_dashboard_preferences SET layout_json=? WHERE operator_id=? AND SHA2(layout_json,256)=?');
    $q->execute([$json,$operator,$revision]);
    if($q->rowCount()===0){
      if($revision!==hash('sha256',''))throw new \DomainException('Dashboard gewijzigd; laad opnieuw.');
      try{$q=$this->db->prepare('INSERT INTO office_dashboard_preferences(operator_id,layout_json) VALUES (?,?)');$q->execute([$operator,$json]);}
      catch(\PDOException $e){throw new \DomainException('Dashboard gewijzigd; laad opnieuw.',0,$e);}
    }
  }
}
