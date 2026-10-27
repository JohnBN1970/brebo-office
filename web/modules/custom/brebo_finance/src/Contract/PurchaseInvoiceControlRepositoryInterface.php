<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Contract;

// Compatibility alias: both runtimes execute the same framework-independent core.
require_once dirname(__DIR__, 6) . '/packages/brebo-finance-core/bootstrap.php';
class_alias(\Brebo\Finance\Contract\PurchaseInvoiceControlRepositoryInterface::class, __NAMESPACE__ . '\\PurchaseInvoiceControlRepositoryInterface');
