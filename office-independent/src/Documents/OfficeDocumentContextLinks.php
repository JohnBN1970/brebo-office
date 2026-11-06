<?php
declare(strict_types=1);

namespace Brebo\Office\Documents;

use Brebo\Office\Intake\OfficeMount;
use InvalidArgumentException;

/**
 * Build a contextual new-document link without trusting arbitrary return URLs.
 * The target endpoint must independently authorize access to the dossier.
 */
final class OfficeDocumentContextLinks
{
    public static function newDocument(string $type, string $id): string
    {
        $parameters = [
            'project' => 'project_id',
            'building' => 'building_id',
            'organization' => 'organization_id',
            'contact' => 'contact_id',
            'personnel' => 'person_id',
            'brebo' => 'brebo_id',
        ];
        if (!isset($parameters[$type]) || $id === '' || strlen($id) > 100
            || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]*$/D', $id)) {
            throw new InvalidArgumentException('invalid_document_dossier_link');
        }
        return OfficeMount::route('documenten/nieuw', [$parameters[$type] => $id]);
    }
}
