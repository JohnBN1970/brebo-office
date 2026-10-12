<?php

declare(strict_types=1);

use Brebo\Office\Intake\WebsiteOfficeCrmAccessPolicy;
use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use Brebo\Office\Intake\WebsiteCrmOperatorSessionProviderInterface;

require_once __DIR__ . '/../src/Intake/WebsiteCrmOperatorContext.php';
require_once __DIR__ . '/../src/Intake/WebsiteCrmOperatorSessionProviderInterface.php';
require_once __DIR__ . '/../src/Intake/WebsiteOfficeCrmAccessPolicy.php';

$provider = new class implements WebsiteCrmOperatorSessionProviderInterface {
  public ?WebsiteCrmOperatorContext $operator = null;

  public function currentOperator(): ?WebsiteCrmOperatorContext {
    return $this->operator;
  }
};
$policy = new WebsiteOfficeCrmAccessPolicy($provider);
foreach ([null, new WebsiteCrmOperatorContext('employee-1', 'session-1', false)] as $identity) {
  $provider->operator = $identity;
  try {
    $policy->requireReader();
    throw new RuntimeException('CRM read access granted without permission.');
  }
  catch (DomainException) {
    // Expected.
  }
}
$provider->operator = new WebsiteCrmOperatorContext('employee-2', 'session-2', true);
if ($policy->requireReader()->operatorId !== 'employee-2') {
  throw new RuntimeException('Verified CRM editor was denied read access.');
}
echo "Independent CRM access policy checks passed.\n";
