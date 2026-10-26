<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use InvalidArgumentException;
use RuntimeException;

/** The operator context is supplied exclusively by the Office session provider. */
final class OnSiteOfficeZonePostAction {
  public function __construct(private OnSiteOfficeCircleCreateService $service) {}

  /** @return array{zone_id:string,project_id:string,building_id:string} */
  public function handle(string $method, string $host, string $allowedHost, string $origin,
    string $contentType, string $body, WebsiteCrmOperatorContext $actor, string $secret, int $now): array {
    if ($method !== 'POST') throw new RuntimeException('zone_method_not_allowed');
    if ($allowedHost === '' || !hash_equals($allowedHost, $host)
      || !hash_equals('https://'.$allowedHost, $origin)) throw new RuntimeException('zone_origin_denied');
    if (strtolower(trim(explode(';', $contentType)[0])) !== 'application/x-www-form-urlencoded') {
      throw new RuntimeException('zone_media_type_unsupported');
    }
    if (strlen($body) > 4096) throw new RuntimeException('zone_payload_too_large');
    $fields=['project_id','building_id','zone_name','latitude','longitude','radius_metres','form_token'];
    $keys=[];
    foreach (explode('&', $body) as $pair) {
      $key=urldecode(explode('=', $pair, 2)[0]);
      if (!in_array($key, $fields, true) || isset($keys[$key])) throw new InvalidArgumentException('invalid_zone_form_fields');
      $keys[$key]=true;
    }
    parse_str($body, $post);
    if (count($post)!==count($fields)) throw new InvalidArgumentException('invalid_zone_form_fields');
    foreach ($post as $value) if (!is_string($value)) throw new InvalidArgumentException('invalid_zone_form_fields');
    $project=$post['project_id'];
    $building=$post['building_id'];
    $key=OnSiteOfficeZoneFormToken::requestKey($post['form_token'], $actor, $project, $building, $secret, $now);
    unset($post['project_id'], $post['form_token']);
    $id=$this->service->create($actor, $project, $post, $key);
    return ['zone_id'=>$id, 'project_id'=>$project, 'building_id'=>$building];
  }
}
