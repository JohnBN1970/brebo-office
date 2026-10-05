<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_mail_intake\Contract\MailSignatureReadRepositoryInterface;

/** Builds a sender-specific signature from canonical user and company data. */
final class MailSignatureBuilder {

  public function __construct(
    private readonly MailSignatureReadRepositoryInterface $signatureRepository,
  ) {}

  /** @return array{name:string,roles:string,company:string,email:string,phone:string,address:string} */
  public function build(int $communicationId): array {
    $source = $this->signatureRepository->signatureSource($communicationId);

    return [
      'name' => $source['name'],
      'roles' => implode(' · ', $source['role_labels']),
      'company' => $source['company'],
      'email' => $source['email'],
      'phone' => $source['phone'],
      'address' => $source['address'],
    ];
  }

}
