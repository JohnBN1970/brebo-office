<?php
declare(strict_types=1);
namespace Brebo\Office\Intake;
use PDO;
final class WebsiteOfficeOperatorAuthenticator {
  public function __construct(private readonly PDO $db) {}
  public function authenticate(string $username,string $password): ?string {
    if($username===''||$password==='')return null;
    $q=$this->db->prepare('SELECT id,password_hash FROM office_operator_accounts WHERE username=? AND active=1 LIMIT 1');
    $q->execute([$username]);$account=$q->fetch(PDO::FETCH_ASSOC);
    if(!is_array($account)||!is_string($account['password_hash']??null)||!password_verify($password,$account['password_hash']))return null;
    return (string)$account['id'];
  }
}
