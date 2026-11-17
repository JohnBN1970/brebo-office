<?php
declare(strict_types=1);
namespace Brebo\Office\Intake;
final class WebsiteOfficeLoginCsrf {
  public static function issue(): string {return bin2hex(random_bytes(32));}
  public static function verify(mixed $submitted,mixed $cookie): bool {
    return is_string($submitted)&&is_string($cookie)&&strlen($submitted)===64&&strlen($cookie)===64&&hash_equals($cookie,$submitted);
  }
  public static function cookieOptions(int $expires): array {
    return ['expires'=>$expires,'path'=>OfficeMount::cookiePath('login_csrf'),'secure'=>true,'httponly'=>true,'samesite'=>'Strict'];
  }
}
