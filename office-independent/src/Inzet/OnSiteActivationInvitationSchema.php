<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Office-owned, single-use activation invitations; never store plaintext secrets. */
final class OnSiteActivationInvitationSchema {
  public static function statements(): array {
    return ["CREATE TABLE office_onsite_activation_invitations (
      token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
      person_id BIGINT UNSIGNED NOT NULL,
      expires_at DATETIME NOT NULL,
      consumed_at DATETIME NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_onsite_invitation_person (person_id),
      CONSTRAINT fk_onsite_invitation_person FOREIGN KEY (person_id) REFERENCES office_inzet_people(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"];
  }
}
