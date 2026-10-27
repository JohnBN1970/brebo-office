<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
/** Review-only CSRF proof, scoped to one authenticated actor and evidence pair. */
final class OnSiteHoursReviewToken {
  private static function sign(string $prefix,WebsiteCrmOperatorContext $actor,array $context,string $secret): string {
    if (strlen($secret)<32||count($context)!==4) throw new \InvalidArgumentException('invalid_hours_form_context');
    return hash_hmac('sha256',json_encode(['office-hours-review:v1',$prefix,$actor->operatorId,$actor->sessionId,array_values($context)],JSON_THROW_ON_ERROR),$secret);
  }
  public static function issue(WebsiteCrmOperatorContext $actor,array $context,string $secret,int $now): string {
    $prefix=($now+1800).'.'.bin2hex(random_bytes(32));
    return $prefix.'.'.self::sign($prefix,$actor,$context,$secret);
  }
  public static function requestKey(mixed $token,WebsiteCrmOperatorContext $actor,array $context,string $secret,int $now): string {
    if (!is_string($token)||!preg_match('/^([0-9]{10})\.([a-f0-9]{64})\.([a-f0-9]{64})$/D',$token,$m)
      ||(int)$m[1]<=$now||(int)$m[1]>$now+1800||!hash_equals(self::sign($m[1].'.'.$m[2],$actor,$context,$secret),$m[3])) throw new \InvalidArgumentException('invalid_hours_form_token');
    return hash('sha256',$token);
  }
}
