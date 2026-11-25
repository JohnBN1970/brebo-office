<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Infrastructure;

use Brebo\MailGateway\Contract\GatewayProvisioningRepositoryInterface;
use Brebo\MailGateway\Contract\GatewayStateReaderInterface;
use PDO;
use RuntimeException;

final class SqliteGatewayProvisioningRepository implements GatewayProvisioningRepositoryInterface, GatewayStateReaderInterface {

  public function __construct(private readonly PDO $pdo) {
    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->migrate();
  }

  public function provisionDomain(array $payload): string {
    $domain = (string) $payload['domain'];
    $reference = 'domain:' . substr(hash('sha256', $domain), 0, 24);

    $statement = $this->pdo->prepare(
      'INSERT INTO mail_domain (domain, reference, payload_json, changed_at)
       VALUES (:domain, :reference, :payload, :changed)
       ON CONFLICT(domain) DO UPDATE SET reference = excluded.reference, payload_json = excluded.payload_json, changed_at = excluded.changed_at'
    );
    $statement->execute([
      ':domain' => $domain,
      ':reference' => $reference,
      ':payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
      ':changed' => time(),
    ]);

    return $reference;
  }

  public function provisionMailbox(array $payload): string {
    $address = (string) $payload['address'];
    $domain = substr(strrchr($address, '@') ?: '', 1);
    if ($domain === '' || !$this->domainExists($domain)) {
      throw new RuntimeException('Mailboxdomein is nog niet geprovisioneerd op de gateway.');
    }

    $reference = 'mailbox:' . substr(hash('sha256', $address), 0, 24);
    $statement = $this->pdo->prepare(
      'INSERT INTO mailbox (address, reference, payload_json, changed_at)
       VALUES (:address, :reference, :payload, :changed)
       ON CONFLICT(address) DO UPDATE SET reference = excluded.reference, payload_json = excluded.payload_json, changed_at = excluded.changed_at'
    );
    $statement->execute([
      ':address' => $address,
      ':reference' => $reference,
      ':payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
      ':changed' => time(),
    ]);

    return $reference;
  }

  public function mailboxReference(string $address): ?string {
    $statement = $this->pdo->prepare('SELECT reference FROM mailbox WHERE address = :address');
    $statement->execute([':address' => $address]);
    $reference = $statement->fetchColumn();
    return is_string($reference) ? $reference : null;
  }

  public function testMailboxPasswordStatus(
    string $address,
    string $reference
  ): array {
    if (!filter_var($address, FILTER_VALIDATE_EMAIL)
        || !str_ends_with($address, '@testmail.brebobv.nl')) {
      throw new RuntimeException('Ongeldig testmailboxadres.');
    }

    $actual = $this->mailboxReference($address);
    if ($actual === null || !hash_equals($actual, $reference)) {
      throw new RuntimeException('Mailboxreferentie komt niet overeen.');
    }

    $exists = $this->pdo->query(
      "SELECT 1 FROM sqlite_master
       WHERE type='table' AND name='mailbox_credential'"
    )->fetchColumn();

    $changed = false;

    if ($exists !== false) {
      $stmt = $this->pdo->prepare(
        'SELECT changed_at FROM mailbox_credential
         WHERE address=?'
      );
      $stmt->execute([$address]);
      $changed = $stmt->fetchColumn();
    }

    return [
      'address' => $address,
      'reference' => $reference,
      'password_set' => $changed !== false,
      'changed_at' => $changed === false ? null : (int)$changed,
    ];
  }

  public function testMailboxActivationStatus(
    string $address,
    string $reference
  ): array {
    if (!filter_var($address, FILTER_VALIDATE_EMAIL)
        || !str_ends_with($address, '@testmail.brebobv.nl')) {
      throw new RuntimeException('Ongeldig testmailboxadres.');
    }

    $actual = $this->mailboxReference($address);

    if ($actual === null || !hash_equals($actual, $reference)) {
      throw new RuntimeException('Ongeldige mailboxreferentie.');
    }

    $stmt = $this->pdo->prepare(
      'SELECT desired_active, applied_active, status,
       revision, requested_at, applied_at, error_code
       FROM mailbox_activation WHERE address=?'
    );
    $stmt->execute([$address]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
      'address'=>$address,
      'reference'=>$reference,
      'desired_active'=>$row ? (bool)$row['desired_active'] : null,
      'applied_active'=>$row ? (bool)$row['applied_active'] : null,
      'status'=>$row ? $row['status'] : 'unverified',
      'revision'=>$row ? (int)$row['revision'] : 0,
      'requested_at'=>$row['requested_at']??null,
      'applied_at'=>$row['applied_at']??null,
      'error_code'=>$row['error_code']??null,
    ];
  }

  public function requestTestMailboxActivation(
    string $address,
    string $reference,
    bool $active
  ): array {

    $bootstrap = $this->pdo->query(
      "SELECT COUNT(*) FROM mailbox_activation_bootstrap
       WHERE id=1 AND scope='testmail.brebobv.nl'
       AND verified_at>0"
    );

    if ((int)$bootstrap->fetchColumn() !== 1) {
      throw new RuntimeException(
        'Mailbox activation bootstrap not verified.'
      );
    }

    if (!preg_match('/^[a-z0-9][a-z0-9._+%-]*@testmail\.brebobv\.nl$/D', $address)
        || !filter_var($address, FILTER_VALIDATE_EMAIL)
        || str_contains($address, '..')) {
      throw new RuntimeException('Ongeldig testmailboxadres.');
    }

    $this->pdo->exec('PRAGMA busy_timeout=5000');
    $this->pdo->beginTransaction();

    try {
      $actual = $this->mailboxReference($address);

      if ($actual === null || !hash_equals($actual, $reference)) {
        throw new RuntimeException('Ongeldige mailboxreferentie.');
      }

      if ($active) {
        $password = $this->testMailboxPasswordStatus(
          $address, $reference
        );

        if (!$password['password_set']) {
          throw new RuntimeException('Wachtwoord ontbreekt.');
        }
      }

      $stmt = $this->pdo->prepare(
        'INSERT INTO mailbox_activation
          (address, desired_active, applied_active,
           status, revision, requested_at)
         VALUES (?, ?, 0, ?, 1, ?)
         ON CONFLICT(address) DO UPDATE SET
           desired_active=excluded.desired_active,
           status=excluded.status,
           revision=mailbox_activation.revision+1,
           requested_at=excluded.requested_at,
           error_code=NULL
         WHERE mailbox_activation.status NOT IN
           (\'pending\', \'applying\')'
      );

      $stmt->execute([
        $address,
        $active ? 1 : 0,
        'pending',
        time(),
      ]);

      if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Aanvraag wordt al verwerkt.');
      }

      $stmt = $this->pdo->prepare(
        'SELECT desired_active, applied_active, status, revision
         FROM mailbox_activation WHERE address=?'
      );
      $stmt->execute([$address]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);

      $this->pdo->commit();

      return [
        'address'=>$address,
        'reference'=>$reference,
        'desired_active'=>(bool)$result['desired_active'],
        'applied_active'=>(bool)$result['applied_active'],
        'status'=>$result['status'],
        'revision'=>(int)$result['revision'],
      ];

    } catch (\Throwable $error) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $error;
    }
  }

  public function storeTestMailboxPassword(
    string $address,
    string $reference,
    string $password
  ): void {
    if (!filter_var($address, FILTER_VALIDATE_EMAIL)
        || !str_ends_with($address, '@testmail.brebobv.nl')
        || strlen($password) < 6
        || strlen($password) > 1024
        || str_contains($password, chr(0))
        || !defined('PASSWORD_ARGON2ID')) {
      throw new RuntimeException('Ongeldige wachtwoordaanvraag.');
    }

    $hash = password_hash($password, PASSWORD_ARGON2ID);
    $this->pdo->exec('PRAGMA busy_timeout=5000');
    $this->pdo->beginTransaction();

    try {
      $activation = $this->pdo->prepare(
        'SELECT status, applied_active
         FROM mailbox_activation WHERE address=?'
      );

      $activation->execute([$address]);
      $state = $activation->fetch(PDO::FETCH_ASSOC);

      if ($state && (
          in_array($state['status'], ['pending','applying'], true)
          || (bool)$state['applied_active']
      )) {
        throw new RuntimeException(
          'Wachtwoordwijziging tijdelijk niet toegestaan.'
        );
      }

      $actual = $this->mailboxReference($address);
      if ($actual === null || !hash_equals($actual, $reference)) {
        throw new RuntimeException('Mailboxreferentie komt niet overeen.');
      }

      $this->pdo->exec('CREATE TABLE IF NOT EXISTS mailbox_credential (
        address TEXT PRIMARY KEY,
        password_hash TEXT NOT NULL,
        changed_at INTEGER NOT NULL,
        FOREIGN KEY(address) REFERENCES mailbox(address)
      )');

      $statement = $this->pdo->prepare(
        'INSERT INTO mailbox_credential
         (address, password_hash, changed_at)
         VALUES (?, ?, ?)
         ON CONFLICT(address) DO UPDATE SET
         password_hash=excluded.password_hash,
         changed_at=excluded.changed_at'
      );

      $statement->execute([
        $address,
        '{ARGON2ID}' . $hash,
        time(),
      ]);

      $this->pdo->commit();
    } catch (\Throwable $error) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $error;
    }
  }

  public function provisionAlias(string $aliasAddress, string $targetAddress): string {
    if (!$this->mailboxExists($targetAddress)) {
      throw new RuntimeException('Doelmailbox is nog niet geprovisioneerd op de gateway.');
    }

    $reference = 'alias:' . substr(hash('sha256', $aliasAddress . '>' . $targetAddress), 0, 24);
    $statement = $this->pdo->prepare(
      'INSERT INTO mailbox_alias (address, target_address, reference, changed_at)
       VALUES (:address, :target, :reference, :changed)
       ON CONFLICT(address) DO UPDATE SET target_address = excluded.target_address, reference = excluded.reference, changed_at = excluded.changed_at'
    );
    $statement->execute([
      ':address' => $aliasAddress,
      ':target' => $targetAddress,
      ':reference' => $reference,
      ':changed' => time(),
    ]);

    return $reference;
  }

  public function domains(): array {
    $statement = $this->pdo->query('SELECT domain, payload_json FROM mail_domain ORDER BY domain');
    $rows = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $payload = json_decode((string) $row['payload_json'], TRUE);
      if (!is_array($payload)) {
        $payload = [];
      }
      $payload['domain'] = (string) $row['domain'];
      $rows[] = $payload;
    }
    return $rows;
  }

  public function mailboxes(): array {
    $statement = $this->pdo->query('SELECT address, payload_json FROM mailbox ORDER BY address');
    $rows = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $payload = json_decode((string) $row['payload_json'], TRUE);
      if (!is_array($payload)) {
        $payload = [];
      }
      $payload['address'] = (string) $row['address'];
      $rows[] = $payload;
    }
    return $rows;
  }

  public function aliases(): array {
    $statement = $this->pdo->query('SELECT address, target_address FROM mailbox_alias ORDER BY address');
    return array_values($statement->fetchAll(PDO::FETCH_ASSOC));
  }

  private function migrate(): void {
    $this->pdo->exec("CREATE TABLE IF NOT EXISTS mailbox_activation_bootstrap (
      id INTEGER PRIMARY KEY CHECK(id=1),
      verified_at INTEGER NOT NULL,
      scope TEXT NOT NULL CHECK(scope='testmail.brebobv.nl')
    )");

    $this->pdo->exec("CREATE TABLE IF NOT EXISTS mailbox_activation (
      address TEXT PRIMARY KEY,
      desired_active INTEGER NOT NULL DEFAULT 0
        CHECK(desired_active IN (0,1)),
      applied_active INTEGER NOT NULL DEFAULT 0
        CHECK(applied_active IN (0,1)),
      status TEXT NOT NULL DEFAULT 'inactive'
        CHECK(status IN (
          'inactive','pending','applying','active','failed'
        )),
      revision INTEGER NOT NULL DEFAULT 0,
      requested_at INTEGER,
      applied_at INTEGER,
      error_code TEXT,
      FOREIGN KEY(address) REFERENCES mailbox(address)
    )");

    $this->pdo->exec('CREATE TABLE IF NOT EXISTS mail_domain (
      domain TEXT PRIMARY KEY,
      reference TEXT NOT NULL,
      payload_json TEXT NOT NULL,
      changed_at INTEGER NOT NULL
    )');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS mailbox (
      address TEXT PRIMARY KEY,
      reference TEXT NOT NULL,
      payload_json TEXT NOT NULL,
      changed_at INTEGER NOT NULL
    )');
    $this->pdo->exec('CREATE TABLE IF NOT EXISTS mailbox_alias (
      address TEXT PRIMARY KEY,
      target_address TEXT NOT NULL,
      reference TEXT NOT NULL,
      changed_at INTEGER NOT NULL
    )');
  }

  private function domainExists(string $domain): bool {
    $statement = $this->pdo->prepare('SELECT 1 FROM mail_domain WHERE domain = :domain LIMIT 1');
    $statement->execute([':domain' => $domain]);
    return $statement->fetchColumn() !== FALSE;
  }

  private function mailboxExists(string $address): bool {
    $statement = $this->pdo->prepare('SELECT 1 FROM mailbox WHERE address = :address LIMIT 1');
    $statement->execute([':address' => $address]);
    return $statement->fetchColumn() !== FALSE;
  }
}
