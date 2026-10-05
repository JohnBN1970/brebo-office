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
