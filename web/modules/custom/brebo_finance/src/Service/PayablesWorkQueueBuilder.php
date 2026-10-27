<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

// Compatibility alias: both runtimes execute the same framework-independent core.
require_once dirname(__DIR__, 6) . '/packages/brebo-finance-core/bootstrap.php';
class_alias(\Brebo\Finance\Service\PayablesWorkQueueBuilder::class, __NAMESPACE__ . '\\PayablesWorkQueueBuilder');
