<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Application boundary for CRM draft editing. Auth adapter must supply a
 * verified WebsiteCrmOperatorContext, never user-controlled session data.
 */
final class WebsiteCrmDraftApplicationService {

  public function __construct(
    private readonly WebsiteCrmDraftCsrf $csrf,
    private readonly WebsiteCrmDraftSubmissionHandler $handler,
  ) {}

  public function formToken(WebsiteCrmOperatorContext $operator, int $opportunityId, int $now): string {
    $operator->requireCrmEdit();
    return $this->csrf->issue($operator->sessionId, $opportunityId, $now);
  }

  /** @param array<string,mixed> $post */
  public function save(WebsiteCrmOperatorContext $operator, array $post, int $now): int {
    $operator->requireCrmEdit();
    return $this->handler->submit($post, $operator->sessionId, $now, true);
  }
}
