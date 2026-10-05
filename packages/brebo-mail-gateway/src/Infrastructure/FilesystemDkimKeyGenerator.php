<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Infrastructure;

use Brebo\MailGateway\Contract\DkimKeyGeneratorInterface;
use RuntimeException;

final class FilesystemDkimKeyGenerator implements DkimKeyGeneratorInterface {

  public function __construct(private readonly string $keyDirectory) {}

  public function generate(string $domain): array {
    $domain = mb_strtolower(trim($domain));
    $directory = rtrim($this->keyDirectory, DIRECTORY_SEPARATOR);
    if ($directory === '') {
      throw new RuntimeException('DKIM key directory ontbreekt.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0700, TRUE) && !is_dir($directory)) {
      throw new RuntimeException('DKIM key directory kan niet worden aangemaakt.');
    }

    $selector = 'brebo1';
    $safeDomain = preg_replace('/[^a-z0-9.-]+/', '_', $domain) ?: 'domain';
    $privatePath = $directory . DIRECTORY_SEPARATOR . $safeDomain . '.' . $selector . '.pem';

    if (!is_file($privatePath)) {
      $resource = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
      ]);
      if ($resource === FALSE || !openssl_pkey_export($resource, $privateKey)) {
        throw new RuntimeException('DKIM private key kon niet worden gegenereerd.');
      }
      if (file_put_contents($privatePath, $privateKey, LOCK_EX) === FALSE) {
        throw new RuntimeException('DKIM private key kon niet worden opgeslagen.');
      }
      chmod($privatePath, 0600);
    }

    $privateKey = file_get_contents($privatePath);
    if ($privateKey === FALSE) {
      throw new RuntimeException('DKIM private key kon niet worden gelezen.');
    }
    $resource = openssl_pkey_get_private($privateKey);
    if ($resource === FALSE) {
      throw new RuntimeException('DKIM private key is ongeldig.');
    }
    $details = openssl_pkey_get_details($resource);
    $public = (string) ($details['key'] ?? '');
    if ($public === '') {
      throw new RuntimeException('DKIM public key kon niet worden afgeleid.');
    }

    $public = preg_replace('/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $public) ?? '';
    return [
      'selector' => $selector,
      'public_key' => $public,
      'private_key_reference' => 'file://' . $privatePath,
    ];
  }
}
