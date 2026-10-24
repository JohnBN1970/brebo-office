<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;

final class OfficeInzetOperationalReviewCsrf {
  public static function token(WebsiteCrmOperatorContext $operator, string $id, int $revision, string $snapshot, string $secret): string {
    if (strlen($secret)<32 || !preg_match('/^[a-f0-9]{64}$/D',$operator->sessionId) || !preg_match('/^[a-f0-9]{64}$/D',$snapshot) || $id==='' || $revision<1) {
      throw new \InvalidArgumentException('Operational form configuration required');
    }
    return hash_hmac('sha256',json_encode(['operational-review-v1',$operator->operatorId,$operator->sessionId,$id,$revision,$snapshot],JSON_THROW_ON_ERROR),$secret);
  }
  public static function verify(mixed $value, WebsiteCrmOperatorContext $operator, string $id, int $revision, string $snapshot, string $secret): bool {
    return is_string($value) && preg_match('/^[a-f0-9]{64}$/D',$value)===1
      && hash_equals(self::token($operator,$id,$revision,$snapshot,$secret),$value);
  }
}
