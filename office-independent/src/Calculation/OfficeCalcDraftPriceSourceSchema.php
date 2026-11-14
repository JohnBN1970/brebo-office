<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;
final class OfficeCalcDraftPriceSourceSchema
{
    /** Same columns as Calc migrations 002/003; installation only, never HTTP DDL. */
    public static function columns():array
    {
        return ['price_source_type'=>"ENUM('manual','article','recipe','supplier_quote') NOT NULL DEFAULT 'manual'",'office_source_id'=>'VARCHAR(128) NULL','source_reference'=>'VARCHAR(255) NULL','source_supplier'=>'VARCHAR(255) NULL','source_unit_price'=>'DECIMAL(19,4) NULL','source_price_date'=>'DATE NULL','source_document_id'=>'VARCHAR(128) NULL','source_details'=>'TEXT NULL'];
    }
}
