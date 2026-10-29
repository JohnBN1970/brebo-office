<?php

declare(strict_types=1);

namespace Brebo\Office\Finance;

use PDO;

/** Separate financial read source; Office identity and permissions never come from this connection. */
final class OfficeFinanceReadConnection {
  public static function fromEnvironment(): ?PDO {
    $dsn=getenv('OFFICE_FINANCE_READ_PDO_DSN');
    if ($dsn===false || $dsn==='') return null;
    if (!str_starts_with($dsn,'mysql:')) throw new \RuntimeException('Unsupported Finance read source');
    $db=new PDO($dsn,getenv('OFFICE_FINANCE_READ_DB_USER') ?: '',getenv('OFFICE_FINANCE_READ_DB_PASSWORD') ?: '',[
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    self::protect($db);
    return $db;
  }

  public static function protect(PDO $db): void {
    // Enforced by the database, including when credentials also serve the legacy application.
    $db->exec('SET SESSION TRANSACTION READ ONLY');
    $db->beginTransaction();
  }
}
