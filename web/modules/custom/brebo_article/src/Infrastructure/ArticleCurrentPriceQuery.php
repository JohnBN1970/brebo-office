<?php
declare(strict_types=1);
namespace Drupal\brebo_article\Infrastructure;

/** SQL adapter policy: one current EUR base price per supplier article, before paging. */
final class ArticleCurrentPriceQuery
{
    public static function predicate():string
    {
        return "p.currency = 'EUR' AND p.quantity_from <= 1 AND p.valid_from <= :material_price_from
          AND (p.valid_until IS NULL OR p.valid_until = '' OR p.valid_until >= :material_price_until)
          AND NOT EXISTS (
            SELECT 1 FROM {brebo_article_price} newer
            JOIN {brebo_catalog_import} newer_import ON newer_import.id=newer.catalog_import_id
            WHERE newer.supplier_article_id=p.supplier_article_id
              AND newer.currency=p.currency AND newer.quantity_from <= 1
              AND newer_import.status='actief' AND newer.valid_from <= :material_newer_from
              AND (newer.valid_until IS NULL OR newer.valid_until='' OR newer.valid_until >= :material_newer_until)
              AND (newer.valid_from > p.valid_from OR (newer.valid_from=p.valid_from AND newer.id>p.id))
          )";
    }
    public static function arguments(string $date):array
    {
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new \InvalidArgumentException('Invalid price selection date');
        return [':material_price_from'=>$date,':material_price_until'=>$date,':material_newer_from'=>$date,':material_newer_until'=>$date];
    }
}
