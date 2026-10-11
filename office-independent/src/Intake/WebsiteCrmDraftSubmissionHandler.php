<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/** Authenticated caller supplies an operator session identifier and CSRF token. */
final class WebsiteCrmDraftSubmissionHandler {

  public function __construct(
    private readonly WebsiteCrmDraftSubmissionValidator $validator,
    private readonly WebsiteCrmDraftCsrf $csrf,
    private readonly WebsiteCrmOpportunityDraftStore $store,
  ) {}

  /** @param array<string,mixed> $post */
  public function submit(array $post, string $operatorSession, int $now): int {
    $input = $this->validator->validate($post);
    $token = $post['csrf_token'] ?? null;
    if (!is_string($token) || !$this->csrf->verify($token, $operatorSession, $input['id'], $now)) {
      throw new \DomainException('CRM draft request failed CSRF verification.');
    }
    return $this->store->save($input['id'], $input['title'], $input['stage'], $input['version']);
  }
}
