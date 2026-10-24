<?php
declare(strict_types=1);

namespace Brebo\Office\Crm;

use PDO;
use RuntimeException;

/**
 * Minimal Office-native CRM writer. Call only after session, CRM writer ACL,
 * CSRF and schema preflight have been enforced by the HTTP controller.
 * This class deliberately does not expose a public HTTP route.
 */
final class OfficeCrmRepository {
  public function __construct(private PDO $db) {}

  public function createCompany(string $name, ?string $email = null): int {
    $name = trim($name);
    if ($name === '' || strlen($name) > 255) {
      throw new RuntimeException('Ongeldige bedrijfsnaam');
    }
    $email = self::optionalEmail($email);
    $statement = $this->db->prepare(
      'INSERT INTO office_crm_companies (name, email) VALUES (:name, :email)'
    );
    $statement->execute([':name' => $name, ':email' => $email]);
    return (int)$this->db->lastInsertId();
  }

  public function createContact(string $firstName, string $lastName, ?string $email = null): int {
    $firstName = trim($firstName);
    $lastName = trim($lastName);
    if ($firstName === '' || $lastName === '' || strlen($firstName) > 128 || strlen($lastName) > 128) {
      throw new RuntimeException('Ongeldige contactnaam');
    }
    $email = self::optionalEmail($email);
    $statement = $this->db->prepare(
      'INSERT INTO office_crm_contacts (first_name, last_name, email) VALUES (:first_name, :last_name, :email)'
    );
    $statement->execute([':first_name' => $firstName, ':last_name' => $lastName, ':email' => $email]);
    return (int)$this->db->lastInsertId();
  }

  public function linkContactToCompany(int $contactId, int $companyId, string $role = ''): int {
    if ($contactId < 1 || $companyId < 1 || strlen($role) > 128) {
      throw new RuntimeException('Ongeldige relatie');
    }
    $statement = $this->db->prepare(
      'INSERT INTO office_crm_company_contacts (company_id, contact_id, role_label) VALUES (:company, :contact, :role)'
    );
    $statement->execute([':company' => $companyId, ':contact' => $contactId, ':role' => trim($role)]);
    return (int)$this->db->lastInsertId();
  }

  private static function optionalEmail(?string $email): ?string {
    $email = $email === null ? null : trim($email);
    if ($email === '') return null;
    if ($email !== null && (strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
      throw new RuntimeException('Ongeldig e-mailadres');
    }
    return $email;
  }
}
