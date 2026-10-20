<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Review-only device token table, tied to the existing Office person. */
final class OnSiteDeviceTokenSchema {
  public static function statements(): array {
    return [
      "CREATE TABLE office_onsite_device_tokens (
        token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
        person_id BIGINT UNSIGNED NOT NULL,
        expires_at DATETIME NULL,
        revoked_at DATETIME NULL,
        INDEX idx_onsite_device_person (person_id),
        CONSTRAINT fk_onsite_device_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
  }
}
