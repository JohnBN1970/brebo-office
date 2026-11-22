<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

use InvalidArgumentException;

/**
 * Converts only editable CL-204 draft fields to the canonical data contract.
 * Authentication, CSRF, building access and persistence belong to the host.
 */
final class OfficeCl204DraftInput
{
    public static function parse(array $post, array $existing = []): array
    {
        require_once __DIR__.'/OfficeCl204Projectopname.php';
        $input = $post['cl204'] ?? null;
        if (!is_array($input)) throw new InvalidArgumentException('Opnamegegevens ontbreken');
        if (array_keys($input) !== ['controls']) throw new InvalidArgumentException('Onbekende opnamevelden');
        $statuses = $input['controls'] ?? null;
        if (!is_array($statuses) || array_diff(array_keys($statuses), array_keys(OfficeCl204Projectopname::controls()))) {
            throw new InvalidArgumentException('Ongeldige opnamecontroles');
        }
        $controls = [];
        foreach (OfficeCl204Projectopname::controls() as $key => $_definition) {
            $item = $statuses[$key] ?? ['status' => 'open'];
            if (!is_array($item) || array_keys($item) !== ['status']
                || !is_string($item['status'])
                || !in_array($item['status'], OfficeCl204Projectopname::applicability(), true)) {
                throw new InvalidArgumentException('Ongeldige status voor '.$key);
            }
            $controls[$key] = ['status' => $item['status']];
        }
        $rawDisciplines = $post['cl204_disciplines'] ?? '';
        if (!is_string($rawDisciplines) || strlen($rawDisciplines) > 2000) {
            throw new InvalidArgumentException('Ongeldige disciplines');
        }
        $disciplines = array_values(array_unique(array_filter(
            array_map('trim', preg_split('/\\r\\n|\\r|\\n/', $rawDisciplines) ?: []),
            static fn(string $s): bool => $s !== ''
        )));
        if (count($disciplines) > 30) throw new InvalidArgumentException('Te veel disciplines');
        foreach ($disciplines as $discipline) {
            if (mb_strlen($discipline) > 100) throw new InvalidArgumentException('Discipline te lang');
        }
        // Preserve non-editable evidence and observations from the stored record.
        // The browser must not be able to forge a release decision or overwrite links.
        $data = $existing;
        $data['controls'] = $controls;
        $data['disciplines'] = $disciplines;
        $errors = OfficeCl204Projectopname::validateDraft($data);
        if ($errors !== []) throw new InvalidArgumentException('Ongeldige opname: '.implode(', ',array_keys($errors)));
        return $data;
    }
}
