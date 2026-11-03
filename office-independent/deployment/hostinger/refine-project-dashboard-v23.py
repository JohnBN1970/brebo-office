#!/usr/bin/env python3
"""Guarded V2.3 server-rendered cleanup; no JavaScript and no controller changes."""
from pathlib import Path
import sys
src,dst=map(Path,sys.argv[1:3])
s=src.read_text()
for marker in ("BREBO_DASHBOARD_V2_MERGE","BREBO_DASHBOARD_V21_LAYOUT","create_project_contract","create_project_term","create_project_change","OfficeProjectFinanceOverview"):
    if marker not in s: raise SystemExit("MISSING:"+marker)
if "BREBO_DASHBOARD_V23_SERVER" in s: raise SystemExit("ALREADY_V23")
needle="$dashboardVisibility = '<style>'"
if s.count(needle)!=1: raise SystemExit("BAD_ANCHOR")
# PHP output buffering runs on the final HTML, after all project actions have executed.
# DOM operations move existing nodes (never discard them) to an accessible disclosure.
injection=r'''// BREBO_DASHBOARD_V23_SERVER: native HTML cleanup, no inline scripts.
$dashboardCss .= '<style>'
  . '.brebo-extra-functions{margin:16px 0;padding:12px 16px;border:1px solid #dbe1e8;border-radius:12px;background:#f8fafc}'
  . '.brebo-extra-functions summary{cursor:pointer;font-weight:650;color:#30516d}'
  . '.brebo-extra-functions[open]>*:not(summary){margin-top:14px}'
  . '.brebo-extra-functions a{margin-right:10px}'
  . '</style>';
// Buffer only the final presentation echo. Do not buffer request processing.
$breboV23Render = static function (string $html): string {
  if (!class_exists('DOMDocument')) return $html;
  $previous = libxml_use_internal_errors(true);
  try {
    $dom = new \\DOMDocument();
    if (!$dom->loadHTML('<?xml encoding="utf-8"?>' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) return $html;
    $xp = new \\DOMXPath($dom);
    $root = $xp->query('//*[@id="project-gegevens"]')->item(0);
    if (!$root) return $html;
    $details = $dom->createElement('details');
    $details->setAttribute('class','brebo-extra-functions');
    $summary = $dom->createElement('summary','Aanvullende projectfuncties');
    $details->appendChild($summary);
    // Select only legacy nav inside project data, not the five primary tabs.
    $navs = $xp->query('.//nav', $root);
    $move = [];
    foreach ($navs as $nav) $move[] = $nav;
    foreach ($move as $nav) $details->appendChild($nav);
    // GOTIK section: its own container; never move the entire project card.
    foreach ($xp->query('.//*[self::h2 or self::h3][contains(translate(normalize-space(.),"abcdefghijklmnopqrstuvwxyz","ABCDEFGHIJKLMNOPQRSTUVWXYZ"),"GOTIK")]', $root) as $heading) {
      $parent = $heading->parentNode;
      if ($parent && $parent !== $root && $parent->parentNode) {
        $details->appendChild($parent);
      }
    }
    if ($details->childNodes->length > 1) $root->appendChild($details);
    $out=$dom->saveHTML();
    return is_string($out) ? preg_replace('/^<\\?xml encoding="utf-8"\\?>\\s*/','',$out) : $html;
  } finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
};
'''
# Fix doubled namespace slash in emitted PHP class names.
injection=injection.replace('\\\\DOMDocument','\\DOMDocument').replace('\\\\DOMXPath','\\DOMXPath')
s=s.replace(needle,injection+needle,1)
# Wrap the one final HTML echo with buffering and transform.
echo="echo '<!doctype html>"
if s.count(echo)!=1: raise SystemExit("BAD_RENDER_START")
s=s.replace(echo,"ob_start();\n"+echo,1)
end="</body></html>';"
if s.count(end)!=1: raise SystemExit("BAD_RENDER_END")
s=s.replace(end,end+"\necho $breboV23Render((string) ob_get_clean());",1)
if s.split("// BREBO_DASHBOARD_V2_MERGE",1)[0]!=src.read_text().split("// BREBO_DASHBOARD_V2_MERGE",1)[0]: raise SystemExit("CONTROLLER_CHANGED")
dst.write_text(s)
print("V23_SERVER_RENDER_READY")
