<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Infrastructure;

use Brebo\MailGateway\Contract\MailStackAdapterInterface;
use RuntimeException;

final class FilesystemMailStackAdapter implements MailStackAdapterInterface {

  public function __construct(private readonly string $configDirectory) {}

  public function applyDomain(array $domain): void {
    $this->ensureDirectory();
    $name = mb_strtolower(trim((string) ($domain['domain'] ?? '')));
    if ($name === '') {
      throw new RuntimeException('Mailstack domeinnaam ontbreekt.');
    }
    $this->writeJson('domain-' . $this->safe($name) . '.json', $domain);
  }

  public function applyMailbox(array $mailbox): void {
    $this->ensureDirectory();
    $address = mb_strtolower(trim((string) ($mailbox['address'] ?? '')));
    if ($address === '') {
      throw new RuntimeException('Mailstack mailboxadres ontbreekt.');
    }
    $this->writeJson('mailbox-' . $this->safe($address) . '.json', $mailbox);
  }

  public function applyAlias(string $aliasAddress, string $targetAddress): void {
    $this->ensureDirectory();
    $this->writeJson('alias-' . $this->safe($aliasAddress) . '.json', [
      'alias' => mb_strtolower(trim($aliasAddress)),
      'target' => mb_strtolower(trim($targetAddress)),
    ]);
  }

  public function health(): array {
    try {
      $this->ensureDirectory();
      return ['available' => is_writable($this->configDirectory), 'message' => 'Mailstack configdirectory beschikbaar.'];
    }
    catch (RuntimeException $e) {
      return ['available' => FALSE, 'message' => $e->getMessage()];
    }
  }

  private function ensureDirectory(): void {
    if ($this->configDirectory === '') {
      throw new RuntimeException('Mailstack configdirectory ontbreekt.');
    }
    if (!is_dir($this->configDirectory) && !mkdir($this->configDirectory, 0700, TRUE) && !is_dir($this->configDirectory)) {
      throw new RuntimeException('Mailstack configdirectory kan niet worden aangemaakt.');
    }
  }

  /** @param array<string,mixed> $payload */
  private function writeJson(string $filename, array $payload): void {
    $path = rtrim($this->configDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temp = $path . '.tmp';
    if (file_put_contents($temp, $json, LOCK_EX) === FALSE) {
      throw new RuntimeException('Mailstack configuratie kon niet worden geschreven.');
    }
    chmod($temp, 0600);
    if (!rename($temp, $path)) {
      @unlink($temp);
      throw new RuntimeException('Mailstack configuratie kon niet atomair worden gepubliceerd.');
    }
  }

  private function safe(string $value): string {
    return preg_replace('/[^a-z0-9._@-]+/', '_', mb_strtolower($value)) ?: 'entry';
  }
}
