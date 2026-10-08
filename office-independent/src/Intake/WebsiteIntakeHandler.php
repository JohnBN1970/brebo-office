<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/** Coordinates validated website intake without any Drupal service dependency. */
final class WebsiteIntakeHandler {

  public function __construct(
    private readonly WebsiteIntakeValidator $validator,
    private readonly WebsiteLeadRepositoryInterface $leads,
  ) {}

  /**
   * @param array<string, mixed> $payload
   * @return array{opportunity_id:int, duplicate:bool, state:string}
   */
  public function handle(array $payload): array {
    $this->validator->validate($payload);
    return $this->leads->acceptWebsiteLead(
      $payload['request_id'],
      $payload['source'],
      $payload,
    );
  }
}
