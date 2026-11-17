<?php
declare(strict_types=1);
namespace Brebo\Office\Intake;
final class WebsiteOfficeLoginController {
  public function __construct(private readonly WebsiteOfficeOperatorAuthenticator $auth,private readonly WebsiteOfficeCrmSessionIssuer $sessions){}
  public function login(string $username,string $password,int $now): ?string {
    $operator=$this->auth->authenticate(trim($username),$password);
    return $operator===null?null:$this->sessions->issue($operator,$now);
  }
  public static function cookie(string $token,int $expires): array {
    return ['expires'=>$expires,'path'=>OfficeMount::cookiePath('session'),'secure'=>true,'httponly'=>true,'samesite'=>'Strict'];
  }
}
