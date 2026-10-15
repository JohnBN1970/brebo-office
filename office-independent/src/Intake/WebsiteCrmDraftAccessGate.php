<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/** Fail-closed CRM draft access boundary backed by a verified Office session. */
final class WebsiteCrmDraftAccessGate {

  public function __construct(private readonly WebsiteCrmOperatorSessionProviderInterface $sessions) {}

  public function requireEditor(): WebsiteCrmOperatorContext {
    $operator = $this->sessions->currentOperator();
    if ($operator === null) {
      throw new \DomainException('Authenticated Office session required.');
    }
    $operator->requireCrmEdit();
    return $operator;
  }
}
