<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Crm/OfficeCrmRepository.php';
use Brebo\Office\Crm\OfficeCrmRepository;
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
  echo "OfficeCrmRepositoryTest SKIP: pdo_sqlite unavailable\n";
  exit(0);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE office_crm_companies (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT)');
$db->exec('CREATE TABLE office_crm_contacts (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT NOT NULL, last_name TEXT NOT NULL, email TEXT)');
$db->exec('CREATE TABLE office_crm_company_contacts (id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER NOT NULL REFERENCES office_crm_companies(id), contact_id INTEGER NOT NULL REFERENCES office_crm_contacts(id), role_label TEXT NOT NULL)');
$repo = new OfficeCrmRepository($db);
$companyA = $repo->createCompany('Aannemer A', 'info@example.org');
$companyB = $repo->createCompany('Aannemer B');
$contact = $repo->createContact('Jan', 'Jansen', 'jan@example.org');
$repo->linkContactToCompany($contact, $companyA, 'Projectleider');
$repo->linkContactToCompany($contact, $companyB, 'Adviseur');
if ((int)$db->query('SELECT COUNT(*) FROM office_crm_company_contacts')->fetchColumn() !== 2) {
  throw new RuntimeException('N:M relationship failed');
}
$invalid = [
  static fn() => $repo->createCompany(''),
  static fn() => $repo->createCompany('Geldig', 'niet-een-email'),
  static fn() => $repo->createContact('', 'Achternaam'),
  static fn() => $repo->linkContactToCompany(0, $companyA),
  static fn() => $repo->linkContactToCompany($contact, 999999),
];
foreach ($invalid as $attempt) {
  try {
    $attempt();
    throw new RuntimeException('Invalid write unexpectedly succeeded');
  } catch (RuntimeException $e) {
    if ($e->getMessage() === 'Invalid write unexpectedly succeeded') throw $e;
  } catch (PDOException) {
    // Foreign-key rejection is expected.
  }
}
echo "OfficeCrmRepositoryTest OK\n";
