#!/usr/bin/env python3
"""Guarded, idempotent integration of V3 portfolio into repository project list.

Does not alter Inzet, project authorization, SQL, POST handlers, or detail routing.
"""
from pathlib import Path
import sys

MARKER = "// BREBO_PROJECT_PORTFOLIO_V3_INTEGRATION"
OLD_START = "if (!$inzetView || $activeInzetTab === 'overzicht') {\nif ($projects === []) {"
OLD_END = "\nif ($inzetView && $activeInzetTab === 'leveranciers') {"
NEW = """if (!$inzetView) {
  require_once dirname(__DIR__) . '/src/Project/OfficeProjectPageStructure.php';
  require_once dirname(__DIR__) . '/src/Project/OfficeProjectPortfolioView.php';
  echo \\Brebo\\Office\\Project\\OfficeProjectPortfolioView::render($projects, $base);
} elseif ($activeInzetTab === 'overzicht') {
  if ($projects === []) {
    echo '<p>Er zijn nog geen toegankelijke projecten.</p>';
  } else {
    echo '<table><thead><tr><th>Projectcode</th><th>Project</th><th>Status</th><th>Gebouwen</th></tr></thead><tbody>';
    foreach ($projects as $project) {
      $url = $base . '/projecten/detail?id=' . (int) $project['id'] . '#inzet';
      echo '<tr><td>' . $escape($project['code']) . '</td><td><a href="' . $escape($url) . '">' . $escape($project['title']) . '</a></td><td>' . $escape($project['status']) . '</td><td>' . (int) $project['building_count'] . '</td></tr>';
    }
    echo '</tbody></table>';
  }
}
"""

def patch(source: str) -> str:
    if MARKER in source:
        return source
    if source.count(OLD_START) != 1 or source.count(OLD_END) != 1:
        raise ValueError("Expected portfolio boundaries missing or duplicated")
    start = source.index(OLD_START)
    end = source.index(OLD_END, start)
    if "office_project_memberships" not in source[:start]:
        raise ValueError("Project ACL query missing")
    source = source[:start] + MARKER + "\n" + NEW + source[end:]
    style_anchor = "header('Content-Type: text/html; charset=utf-8');"
    if source.count(style_anchor) != 1:
        raise ValueError("Unexpected HTML header structure")
    source = source.replace(style_anchor, """if (!$inzetView) {
  require_once dirname(__DIR__) . '/src/Project/OfficeProjectPortfolioView.php';
}
$portfolioV3Styles = $inzetView ? '' : \\Brebo\\Office\\Project\\OfficeProjectPortfolioView::styles();
""" + style_anchor, 1)
    css_anchor = "</style></head><body>"
    if source.count(css_anchor) != 1:
        raise ValueError("Unexpected CSS boundary")
    source = source.replace(css_anchor, "' . $portfolioV3Styles . '</style></head><body>", 1)
    return source

if __name__ == "__main__":
    if len(sys.argv) != 2:
        raise SystemExit("Usage: integrate-project-portfolio-v3.py <office-projects.php>")
    path = Path(sys.argv[1])
    before = path.read_text()
    after = patch(before)
    if after != before:
        path.write_text(after)
    print("PROJECT_PORTFOLIO_V3_PATCH_OK")
