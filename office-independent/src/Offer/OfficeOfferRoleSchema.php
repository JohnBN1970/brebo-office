<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

/** Optional central-role migration. Never run from a web request. */
final class OfficeOfferRoleSchema
{
    public static function statements(): array
    {
        return [
            "CREATE TABLE office_roles (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                role_key VARCHAR(64) NOT NULL,
                label VARCHAR(128) NOT NULL,
                active TINYINT NOT NULL DEFAULT 1,
                UNIQUE KEY uq_office_role_key (role_key)
            )",
            "CREATE TABLE office_user_roles (
                operator_id VARCHAR(128) NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (operator_id, role_id),
                KEY idx_office_user_roles_role (role_id)
            )",
            "CREATE TABLE office_role_permissions (
                role_id BIGINT UNSIGNED NOT NULL,
                module VARCHAR(32) NOT NULL,
                permission VARCHAR(32) NOT NULL,
                PRIMARY KEY (role_id, module, permission)
            )",
            "CREATE TABLE office_role_scopes (
                role_id BIGINT UNSIGNED NOT NULL,
                module VARCHAR(32) NOT NULL,
                scope_key VARCHAR(32) NOT NULL,
                PRIMARY KEY (role_id, module)
            )",
            "CREATE TABLE office_role_settings_audit (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                actor_operator_id VARCHAR(128) NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                module VARCHAR(32) NOT NULL,
                before_json JSON NOT NULL,
                after_json JSON NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_role_settings_audit_role (role_id, created_at),
                KEY idx_role_settings_audit_actor (actor_operator_id, created_at)
            )",
        ];
    }
}
