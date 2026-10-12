<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/** Authenticate only against Office-owned accounts; never issue sessions here. */
final class WebsiteOfficeOperatorAuthenticator {

  public function __construct(private readonly PDO $db) {}

  /** @return array{operator_id:string,can_edit_crm:bool}|null */
  public function authenticate(string $username, string $password): ?array {
    if ($username === '' || strlen($username) > 190 || $password === '' || strlen($password) > 4096) {
      return null;
    }
    $stmt = $this->db->prepare(
      'SELECT id, password_hash, can_edit_crm, active
       FROM office_operator_accounts WHERE username = :username LIMIT 1'
    );
    $stmt->execute([':username' => $username]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($account === false || (int) $account['active'] !== 1 || !password_verify($password, (string) $account['password_hash'])) {
      return null;
    }
    return [
      'operator_id' => (string) $account['id'],
      'can_edit_crm' => (int) $account['can_edit_crm'] === 1,
    ];
  }
}
