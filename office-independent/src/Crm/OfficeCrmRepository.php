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

  /** @return list<array{id:int,name:string}> */
  public function companiesForSelection(): array {
    $rows = $this->db->query(
      "SELECT id, name FROM office_crm_companies WHERE status = 'active' ORDER BY name, id LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array => [
      'id' => (int)$row['id'], 'name' => (string)$row['name'],
    ], $rows);
  }

  /** @return list<array{id:int,name:string}> */
  public function contactsForSelection(): array {
    $rows = $this->db->query(
      "SELECT id, first_name, last_name FROM office_crm_contacts WHERE status = 'active' ORDER BY last_name, first_name, id LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row): array => [
      'id' => (int)$row['id'], 'name' => trim((string)$row['first_name'] . ' ' . (string)$row['last_name']),
    ], $rows);
  }

  /** @return list<array{id:int,name:string,email:string,contacts:int}> */
  public function listCompanies(): array {
    $rows = $this->db->query(
      'SELECT c.id, c.name, c.email, COUNT(DISTINCT cc.contact_id) AS contacts
       FROM office_crm_companies c
       LEFT JOIN office_crm_company_contacts cc ON cc.company_id = c.id
       GROUP BY c.id, c.name, c.email
       ORDER BY c.name, c.id LIMIT 200'
    )->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $r): array => [
      'id' => (int)$r['id'], 'name' => (string)$r['name'],
      'email' => (string)($r['email'] ?? ''), 'contacts' => (int)$r['contacts'],
    ], $rows);
  }

  /** @return list<array{id:int,name:string,email:string,companies:string}> */
  public function listContacts(): array {
    $rows = $this->db->query(
      "SELECT p.id, p.first_name, p.last_name, p.email,
        GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS companies
       FROM office_crm_contacts p
       LEFT JOIN office_crm_company_contacts cc ON cc.contact_id = p.id
       LEFT JOIN office_crm_companies c ON c.id = cc.company_id
       GROUP BY p.id, p.first_name, p.last_name, p.email
       ORDER BY p.last_name, p.first_name, p.id LIMIT 200"
    )->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $r): array => [
      'id' => (int)$r['id'],
      'name' => trim((string)$r['first_name'] . ' ' . (string)$r['last_name']),
      'email' => (string)($r['email'] ?? ''),
      'companies' => (string)($r['companies'] ?? ''),
    ], $rows);
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
