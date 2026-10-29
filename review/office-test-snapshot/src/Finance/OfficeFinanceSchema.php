<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

/** Explicit installation SQL only. Never executed by HTTP routes. */
final class OfficeFinanceSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_module_permissions (
        operator_id VARCHAR(128) NOT NULL, module VARCHAR(32) NOT NULL,
        permission VARCHAR(32) NOT NULL, PRIMARY KEY(operator_id,module,permission)
      )",
      "CREATE TABLE office_finance_project_mapping (
        source_project_id BIGINT NOT NULL PRIMARY KEY, office_project_id BIGINT NOT NULL UNIQUE,
        verified SMALLINT NOT NULL DEFAULT 0
      )",
    ];
  }
}
