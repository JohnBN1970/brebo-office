<?php
declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Uniforme actiebalk voor Office-overzichten en detailpagina's.
 *
 * Dit is uitsluitend presentatie.
 * Autorisatie en databasewijzigingen blijven bij de module.
 */
final class OfficeRecordActions
{
    private static function escape(string $value): string
    {
        return htmlspecialchars(
            $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    public static function render(
        array $urls,
        array $allowed
    ): string {
        $labels = [
            'new' => '+ Nieuw',
            'edit' => 'Bewerken',
            'archive' => 'Archiveren',
            'delete' => 'Verwijderen',
        ];

        $html = '<nav class="office-record-actions" aria-label="Dossieracties">';

        foreach ($labels as $action => $label) {
            if (($allowed[$action] ?? false) !== true) {
                continue;
            }

            $url = $urls[$action] ?? null;

            if (
                !is_string($url)
                || !str_starts_with($url, '/office-test/')
            ) {
                continue;
            }

            $html .= '<a class="office-record-action office-record-action-'
                . self::escape($action)
                . '" href="'
                . self::escape($url)
                . '">'
                . self::escape($label)
                . '</a>';
        }

        return $html . '</nav>';
    }

    public static function styles(): string
    {
        return '<style>
            .office-record-actions {
                display:flex;
                flex-wrap:wrap;
                align-items:center;
                gap:18px;
                margin:14px 0 20px;
            }
            .office-record-action {
                color:#175a91;
                font-weight:600;
                text-decoration:underline;
                text-underline-offset:3px;
            }
            .office-record-action-delete {
                color:#a21d22;
            }
            .office-record-action:focus-visible {
                outline:2px solid #175a91;
                outline-offset:3px;
            }
        </style>';
    }
}
