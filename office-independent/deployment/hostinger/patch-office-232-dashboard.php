<?php
declare(strict_types=1);

/**
 * Guarded, additive installation of 232-home demonstration planning.
 * Does not replace the server dossier page or modify database records.
 */
if (PHP_SAPI !== 'cli' || count($argv) !== 4) {
    fwrite(STDERR, "DEMO_232_ARGUMENTS_INVALID\n");
    exit(2);
}
[, $beforePath, $afterPath, $livePath] = $argv;
$base = realpath(dirname($livePath));
$expected = realpath((string) getenv('HOME') . '/domains/brebobv.nl/office_calc_test/release-office-test-contractform-20261030/private/public');
if ($base === false || $expected === false || $base !== $expected || is_link($livePath) || !is_file($livePath)) {
    fwrite(STDERR, "DEMO_232_SCOPE_REJECTED\n");
    exit(3);
}
$before = file_get_contents($beforePath);
$after = file_get_contents($afterPath);
$live = file_get_contents($livePath);
if ($before === false || $after === false || $live === false) {
    fwrite(STDERR, "DEMO_232_SOURCE_UNREADABLE\n");
    exit(4);
}
if ($live === $after) {
    fwrite(STDERR, "DEMO_232_ALREADY_INSTALLED\n");
    exit(0);
}
$anchor = '$demoDashboardHtml = ' . chr(39) . chr(39) . ';';
$next = 'if ($isDashboardDemo) {';
$start = strpos($after, $anchor);
$end = $start !== false ? strpos($after, $next, $start) : false;
if ($start === false || $end === false || $end <= $start) {
    fwrite(STDERR, "DEMO_232_EXTENSION_NOT_FOUND\n");
    exit(5);
}
$extension = substr($after, $start, $end - $start);
if (strpos($extension, 'is232Demo') === false || strpos($extension, 'Planning uit Office-test') === false
    || substr_count($before, $anchor) !== 1) {
    fwrite(STDERR, "DEMO_232_SOURCE_EXTENSION_INVALID\n");
    exit(6);
}
if (substr_count($live, $anchor) !== 1) {
    // The active server may use the independent V2/V3 dossier controller.
    $v2Anchor = "  if (" . '$dashboardDemo' . ") {";
    $v2End = "  } else {";
    $v2Start = strpos($live, $v2Anchor);
    $v2Finish = $v2Start !== false ? strpos($live, $v2End, $v2Start) : false;
    if ($v2Start === false || $v2Finish === false
        || substr_count($live, $v2Anchor) !== 1
        || strpos($live, "BREBO_DASHBOARD_V2_MERGE") === false
        || strpos($live, "OfficeNativeProjectAccess") === false) {
        fwrite(STDERR, "DEMO_232_LIVE_VARIANT_UNSUPPORTED\n");
        exit(6);
    }
    $v2Demo = substr($live, $v2Start, $v2Finish - $v2Start);
    if (substr_count($v2Demo, '$dashboardHtml') < 2
        || strpos($v2Demo, 'brebo-dashboard-panels') === false) {
        fwrite(STDERR, "DEMO_232_LIVE_V2_DEMO_STRUCTURE_UNSUPPORTED\n");
        exit(6);
    }
    $insert = <<<'PHP'

    // TEST-2026-001: read-only planning extension for isolated demo.
    if ((string) $project->identity->projectCode === 'TEST-2026-001'
        && (string) $project->title === 'TEST - Renovatie 232 woningen') {
      $dashboardHtml .= '<section class="brebo-dashboard-card" style="margin-top:16px"><h2>Planning uit Office-test · DEMO</h2>';
      try {
        $plan = $db->prepare('SELECT title, planned_start, planned_end, status, critical_note FROM office_project_plan_items WHERE project_id = ? ORDER BY sort_order, planned_start, id');
        $plan->execute([(int) $projectId]);
        $rows = $plan->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
          $dashboardHtml .= '<p>Geen vastgelegde planning gevonden.</p>';
        } else {
          $dashboardHtml .= '<ol>';
          foreach ($rows as $item) {
            $dashboardHtml .= '<li><strong>' . $escape($item['title']) . '</strong> · '
              . $escape($item['planned_start']) . ' t/m ' . $escape($item['planned_end'])
              . ' · ' . $escape($item['status']);
            if (trim((string) ($item['critical_note'] ?? '')) !== '') {
              $dashboardHtml .= '<p>' . $escape($item['critical_note']) . '</p>';
            }
            $dashboardHtml .= '</li>';
          }
          $dashboardHtml .= '</ol>';
        }
      } catch (Throwable $ignored) {
        $dashboardHtml .= '<p>Planning momenteel niet beschikbaar.</p>';
      }
      $dashboardHtml .= '<p>Alleen Office-test; planning/status/risico zijn DEMO.</p></section>';
    }
PHP;
    $patched = substr_replace($live, $insert . "\n", $v2Finish, 0);
} else {
    $oldTail = "    . '</div>';\n}\n" . '$dashboardHtml =';
    $newTail = "    . " . '$demoPlanHtml' . "\n    . '</div>';\n}\n" . '$dashboardHtml =';
    if (substr_count($before, $oldTail) !== 1
        || substr_count($after, $newTail) !== 1
        || substr_count($live, $oldTail) !== 1) {
        fwrite(STDERR, "DEMO_232_STRUCTURE_CHANGED_ABORT\n");
        exit(8);
    }
    $patched = str_replace($anchor, $extension, $live);
    $patched = str_replace($oldTail, $newTail, $patched);
}
if ($patched === $live || substr_count($patched, 'Planning uit Office-test') !== 1) {
    fwrite(STDERR, "DEMO_232_PATCH_UNSAFE\n");
    exit(9);
}
$dir = dirname($livePath);
$temp = tempnam($dir, '.demo232-');
if ($temp === false) {
    fwrite(STDERR, "DEMO_232_TEMP_FAILURE\n");
    exit(10);
}
try {
    if (file_put_contents($temp, $patched) === false) {
        throw new RuntimeException('WRITE_FAILED');
    }
    chmod($temp, fileperms($livePath) & 0777);
    // Return the candidate and the original page hash to the shell workflow.
    // PHP exec/proc_open are deliberately not used (disabled on Hostinger).
    echo hash('sha256', $live) . '|' . $temp . "\n";
} catch (Throwable $exception) {
    @unlink($temp);
    fwrite(STDERR, "DEMO_232_" . $exception->getMessage() . "\n");
    exit(11);
}
