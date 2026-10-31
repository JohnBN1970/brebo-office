<?php

declare(strict_types=1);

namespace Brebo\Office\Offer;

/** Explicit migration only; never run on a web request. */
final class OfficeOfferChangeLinkSchema
{
    public static function statements(): array
    {
        return [
            "CREATE TABLE office_offer_change_links (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                acceptance_id BIGINT UNSIGNED NOT NULL,
                offer_id BIGINT UNSIGNED NOT NULL,
                change_order_id BIGINT UNSIGNED NOT NULL,
                office_project_id BIGINT UNSIGNED NOT NULL,
                linked_by_operator_id VARCHAR(128) NOT NULL,
                linked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_offer_link_acceptance (acceptance_id),
                UNIQUE KEY uq_offer_link_offer (offer_id),
                UNIQUE KEY uq_offer_link_change (change_order_id),
                KEY idx_offer_link_project (office_project_id)
            )"
        ];
    }
}
