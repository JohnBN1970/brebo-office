<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use InvalidArgumentException;
use RuntimeException;

/** Strict form boundary for revision-protected building-zone updates. */
final class OnSiteOfficeZoneUpdatePostAction {
  public function __construct(private OnSiteOfficeCircleUpdateService $service) {}

  public function handle(string $contentType,string $body,WebsiteCrmOperatorContext $actor,
    string $secret,int $now): array {
    if (strtolower(trim(explode(';',$contentType)[0]))!=='application/x-www-form-urlencoded')
      throw new RuntimeException('zone_media_type_unsupported');
    if (strlen($body)>4096) throw new RuntimeException('zone_payload_too_large');
    $allowed=['project_id','building_id','zone_id','revision','zone_name','latitude','longitude','radius_metres','form_token'];
    $seen=[];
    foreach (explode('&',$body) as $pair) {
      $key=urldecode(explode('=',$pair,2)[0]);
      if (!in_array($key,$allowed,true) || isset($seen[$key])) throw new InvalidArgumentException('invalid_zone_update_fields');
      $seen[$key]=true;
    }
    parse_str($body,$post);
    if (count($post)!==count($allowed)) throw new InvalidArgumentException('invalid_zone_update_fields');
    foreach ($post as $value) if (!is_string($value)) throw new InvalidArgumentException('invalid_zone_update_fields');
    foreach (['project_id','building_id','zone_id','revision'] as $key)
      if (!preg_match('/^[1-9][0-9]{0,18}$/D',$post[$key])) throw new InvalidArgumentException('invalid_zone_update_fields');
    $revision=filter_var($post['revision'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!is_int($revision)) throw new InvalidArgumentException('invalid_zone_update_fields');
    OnSiteOfficeZoneFormToken::requestKey($post['form_token'],$actor,$post['project_id'],$post['building_id'],$secret,$now);
    $changes=array_intersect_key($post,array_flip(['zone_name','latitude','longitude','radius_metres']));
    $updated=$this->service->update($actor,$post['project_id'],$post['building_id'],$post['zone_id'],$revision,$changes);
    if (!$updated) throw new RuntimeException('zone_revision_conflict');
    return ['project_id'=>$post['project_id'],'building_id'=>$post['building_id'],'zone_id'=>$post['zone_id']];
  }
}
