<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

use InvalidArgumentException;
use RuntimeException;

/**
 * Decision policy only: caller MUST check authenticated identity, actual
 * authorization/mandate and contextual scope server-side before transition.
 * No form is auto-approved or released.
 */
final class OfficeProcessFormWorkflow
{
    private const TRANSITIONS = [
        'concept' => ['submit'=>'ingediend'],
        'ingediend' => ['start_review'=>'controle','return'=>'concept'],
        'controle' => ['approve'=>'akkoord','reject'=>'afgekeurd','return'=>'concept'],
        'afgekeurd' => ['reopen'=>'concept'],
        'akkoord' => ['reopen'=>'concept'],
    ];

    public static function target(string $status, string $action, string $actor, string $submitter, bool $fourEyes, bool $mandateGranted, string $reason): string
    {
        $next = self::TRANSITIONS[$status][$action] ?? null;
        if ($next === null) throw new InvalidArgumentException('Statusovergang niet toegestaan');
        if (!$mandateGranted) throw new RuntimeException('Onvoldoende mandaat');
        if (trim($actor)==='' || trim($reason)==='' || mb_strlen($reason)>500) {
            throw new InvalidArgumentException('Gebruiker en reden zijn verplicht');
        }
        if ($fourEyes && $action==='approve' && ($submitter==='' || hash_equals($submitter,$actor))) {
            throw new RuntimeException('Onafhankelijke controleur vereist');
        }
        return $next;
    }

    public static function actions(string $status): array
    {
        return self::TRANSITIONS[$status] ?? [];
    }
}
