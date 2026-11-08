<?php
declare(strict_types=1);

namespace Brebo\Office\Documents;

require_once __DIR__ . '/OfficeDocumentTypes.php';

/** Reusable document creation dropdown; rendering never grants authorization. */
final class OfficeNewDocumentMenu
{
    public static function render(string $context, array $scope = []): string
    {
        $types = OfficeDocumentTypes::forContext($context);
        if ($types === []) {
            return '';
        }
        $query = [];
        foreach (['project_id', 'building_id'] as $key) {
            if (!isset($scope[$key])) {
                continue;
            }
            $value = (string) $scope[$key];
            if (!preg_match('/^[1-9][0-9]*$/D', $value)) {
                continue;
            }
            $query[$key] = $value;
        }
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<details class="office-new-document"><summary>+ Nieuw document</summary><ul>';
        foreach ($types as $type => $label) {
            if ($type === 'bouwkundige-inspectie') {
                $url = '/office-test/inspectie';
                if ($query !== []) {
                    $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
                }
                $html .= '<li><a href="' . $escape($url) . '">' . $escape($label) . '</a></li>';
            } else {
                // Not yet wired: never offer a clickable route that cannot create a document.
                $html .= '<li><span aria-disabled="true" title="Nog niet aangesloten">' . $escape($label) . ' (binnenkort)</span></li>';
            }
        }
        return $html . '</ul></details>';
    }
}
