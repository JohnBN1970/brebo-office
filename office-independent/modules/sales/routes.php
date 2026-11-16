<?php
declare(strict_types=1);
// Publish the route only after the guarded schema and source installation.
if (getenv('OFFICE_SALES_ENABLED') !== 'enabled' || getenv('OFFICE_SALES_SCHEMA_VERIFIED') !== 'enabled') return [];
return ['/office-test/finance/verkoop' => 'office-sales.php'];
