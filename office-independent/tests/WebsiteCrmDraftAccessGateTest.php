<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteCrmDraftAccessGate;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Intake\WebsiteCrmOperatorSessionProviderInterface;

foreach (['WebsiteCrmOperatorContext', 'WebsiteCrmOperatorSessionProviderInterface', 'WebsiteCrmDraftAccessGate'] as $name) {
  require_once __DIR__ . '/../src/Intake/' . $name . '.php';
}
foreach ([null, new WebsiteCrmOperatorContext('viewer', 'session-v', false)] as $identity) {
  $provider = new class($identity) implements WebsiteCrmOperatorSessionProviderInterface {
    public function __construct(private readonly ?WebsiteCrmOperatorContext $identity) {}
    public function currentOperator(): ?WebsiteCrmOperatorContext { return $this->identity; }
  };
  $denied = false;
  try {
    (new WebsiteCrmDraftAccessGate($provider))->requireEditor();
  }
  catch (DomainException) {
    $denied = true;
  }
  if (!$denied) {
    throw new RuntimeException('Anonymous or read-only Office operator was permitted to edit CRM.');
  }
}
$editor = new WebsiteCrmOperatorContext('editor', 'session-e', true);
$provider = new class($editor) implements WebsiteCrmOperatorSessionProviderInterface {
  public function __construct(private readonly WebsiteCrmOperatorContext $identity) {}
  public function currentOperator(): ?WebsiteCrmOperatorContext { return $this->identity; }
};
if ((new WebsiteCrmDraftAccessGate($provider))->requireEditor() !== $editor) {
  throw new RuntimeException('Verified Office CRM editor was not accepted.');
}
echo "CRM verified-session access gate checks passed.\n";
