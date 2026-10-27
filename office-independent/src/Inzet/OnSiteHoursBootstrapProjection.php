<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use PDO;
/** Enrich an existing verified bootstrap without replacing its zone authority. */
final class OnSiteHoursBootstrapProjection {
  public static function enrich(array $result,PDO $db,string $deviceToken,bool $reviewsEnabled):array {
    if(($result['status']??null)!==200||!$reviewsEnabled)return $result;
    foreach(['OnSiteDeviceTokenRepository','OnSiteHoursReviewReadiness','OnSiteHoursApprovalRepository','OnSiteApprovalProjection']as$class)require_once __DIR__.'/'.$class.'.php';
    $person=(new OnSiteDeviceTokenRepository($db))->resolveEmployee($deviceToken);
    if($person===null||($result['body']['employee']['id']??null)!==$person)throw new \RuntimeException('bootstrap_review_identity_mismatch');
    $q=$db->prepare('SELECT display_name FROM office_inzet_people WHERE id=? AND active=1');$q->execute([$person]);$name=$q->fetchColumn();
    if(!is_string($name)||trim($name)==='')throw new \RuntimeException('bootstrap_review_identity_unavailable');
    $result['body']['employee']['display_name']=trim($name);
    if($reviewsEnabled){
      OnSiteHoursReviewReadiness::requireReady($db);
      $sessions=(new OnSiteHoursApprovalRepository($db))->approvedSessionsForPerson($person);
      $result['body']['approved_events']=(object)OnSiteApprovalProjection::forEmployee($sessions,$person);
    }else{unset($result['body']['approved_events']);}
    return $result;
  }
}
