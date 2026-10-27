<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
final class OnSiteHoursReviewPostAction {
  public function __construct(private OnSiteSessionReviewWriter $writer) {}
  public function handle(string $contentType,string $body,WebsiteCrmOperatorContext $actor,string $secret,int $now): array {
    if (strtolower(trim(explode(';',$contentType)[0]))!=='application/x-www-form-urlencoded') throw new \RuntimeException('hours_review_media_type');
    if (strlen($body)>4096) throw new \RuntimeException('hours_review_payload_limit');
    $required=['project_id','person_id','in_event_id','out_event_id','decision','reason','form_token'];
    $allowed=[...$required,'corrected_seconds','corrected_hours'];
    $seen=[];
    foreach(explode('&',$body) as $pair) {
      $key=urldecode(explode('=',$pair,2)[0]);
      if (!in_array($key,$allowed,true)||isset($seen[$key])) throw new \InvalidArgumentException('invalid_hours_form_fields');
      $seen[$key]=true;
    }
    parse_str($body,$post);
    if (count($post)!==8||array_diff($required,array_keys($post))||isset($post['corrected_seconds'])===isset($post['corrected_hours'])) throw new \InvalidArgumentException('invalid_hours_form_fields');
    foreach($post as $value) if(!is_string($value)) throw new \InvalidArgumentException('invalid_hours_form_fields');
    $key=OnSiteHoursReviewToken::requestKey($post['form_token'],$actor,[$post['project_id'],$post['person_id'],$post['in_event_id'],$post['out_event_id']],$secret,$now);
    $seconds=null;
    if (isset($post['corrected_hours'])) {
      require_once __DIR__.'/OnSiteHoursPresentation.php';
      $seconds=OnSiteHoursPresentation::correction($post['corrected_hours']);
    }elseif ($post['corrected_seconds']!=='') {
      if (!preg_match('/^(?:0|[1-9][0-9]{0,4})$/D',$post['corrected_seconds'])) throw new \InvalidArgumentException('invalid_hours_correction');
      $seconds=(int)$post['corrected_seconds'];
    }
    $result=$this->writer->record($post['person_id'],$post['in_event_id'],$post['out_event_id'],$post['decision'],$seconds,$actor->operatorId,$post['reason'],$post['project_id'],$key);
    return $result+['project_id'=>$post['project_id']];
  }
}
