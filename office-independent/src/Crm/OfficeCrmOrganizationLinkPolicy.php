<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

/**
 * Pure validation for legal organization links. The caller must lock the
 * relevant organization graph and enforce these rules in one DB transaction.
 */
final class OfficeCrmOrganizationLinkPolicy
{
    private const TYPES = ['subsidiary', 'branch', 'group_member'];

    public static function validate(
        int $parentId,
        int $childId,
        string $type,
        array $existingLinks
    ): void {
        if ($parentId < 1 || $childId < 1 || $parentId === $childId) {
            throw new \InvalidArgumentException('Invalid organization endpoints');
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Invalid organization link type');
        }

        // Edges represent parent -> child. Adding parent -> child is invalid
        // when the child already reaches the parent.
        $graph = [];
        foreach ($existingLinks as $edge) {
            $from = (int) ($edge['parent_relation_id'] ?? 0);
            $to = (int) ($edge['child_relation_id'] ?? 0);
            if ($from < 1 || $to < 1) {
                throw new \InvalidArgumentException('Invalid existing organization edge');
            }
            $graph[$from][] = $to;
            if ($type !== 'group_member' && $to === $childId
                && in_array((string) ($edge['link_type'] ?? ''), ['subsidiary', 'branch'], true)
                && $from !== $parentId) {
                throw new \InvalidArgumentException('Child already has a legal parent');
            }
        }

        $pending = [$childId];
        $visited = [];
        while ($pending !== []) {
            $current = array_pop($pending);
            if ($current === $parentId) {
                throw new \InvalidArgumentException('Organization cycle detected');
            }
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;
            foreach ($graph[$current] ?? [] as $next) {
                $pending[] = $next;
            }
        }
    }
}
