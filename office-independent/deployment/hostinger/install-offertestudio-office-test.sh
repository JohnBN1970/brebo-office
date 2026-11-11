#!/usr/bin/env bash
set -euo pipefail
base="$HOME/domains/brebobv.nl/office_calc_test"
router="$HOME/domains/brebobv.nl/public_html/office-test/index.php"
config="$base/config/config.php"
stage="$1"
test -d "$stage" && test -f "$config" && test -f "$router" && test ! -L "$router"
test -f "$stage/office-independent/public/office-quotation-studio.php"
test -f "$stage/office-independent/src/Quotation/OfficeQuotationComposer.php"
test -f "$stage/office-independent/src/Quotation/OfficeQuotationDraftRepository.php"
php_bin="$(command -v php)"
"$php_bin" -l "$stage/office-independent/public/office-quotation-studio.php"
"$php_bin" -l "$stage/office-independent/src/Quotation/OfficeQuotationComposer.php"
"$php_bin" -l "$stage/office-independent/src/Quotation/OfficeQuotationDraftRepository.php"
root="$("$php_bin" -r '$c=require $argv[1]; if(!is_array($c)) exit(4); if(($c["OFFICE_BASE_PATH"]??"")!=="/office-test") exit(5); echo $c["OFFICE_PRIVATE_ROOT"]??"";' "$config")"
case "$root" in "$base"/release-*/private) ;; *) echo ACTIVE_ROOT_NOT_ISOLATED; exit 30;; esac
test -d "$root/public" && test -d "$root/src" || { echo PRIVATE_RELEASE_INVALID; exit 31; }
real="$(realpath "$root")"
case "$real" in "$base"/release-*/private) ;; *) echo REAL_ROOT_NOT_ISOLATED; exit 32;; esac
backup="$base/.quotation-backups/$(date +%Y%m%d%H%M%S)-$$"
mkdir -p "$backup" "$root/src/Quotation"
cp -p "$router" "$backup/index.php"
for name in office-quotation-studio.php; do
  if test -e "$root/public/$name"; then cp -p "$root/public/$name" "$backup/$name"; fi
done
for name in OfficeQuotationComposer.php OfficeQuotationDraftRepository.php; do
  if test -e "$root/src/Quotation/$name"; then cp -p "$root/src/Quotation/$name" "$backup/$name"; fi
done
rollback() {
  echo QUOTATION_INSTALL_ROLLBACK
  cp -p "$backup/index.php" "$router"
  for name in office-quotation-studio.php; do
    if test -f "$backup/$name"; then cp -p "$backup/$name" "$root/public/$name"; else rm -f "$root/public/$name"; fi
  done
  for name in OfficeQuotationComposer.php OfficeQuotationDraftRepository.php; do
    if test -f "$backup/$name"; then cp -p "$backup/$name" "$root/src/Quotation/$name"; else rm -f "$root/src/Quotation/$name"; fi
  done
}
trap rollback ERR
cp -p "$stage/office-independent/public/office-quotation-studio.php" "$root/public/"
cp -p "$stage/office-independent/src/Quotation/OfficeQuotationComposer.php" "$root/src/Quotation/"
cp -p "$stage/office-independent/src/Quotation/OfficeQuotationDraftRepository.php" "$root/src/Quotation/"
"$php_bin" -r '
$p=$argv[1]; $s=file_get_contents($p);
if (!is_string($s)) exit(41);
$line="  \x27/office-test/crm/offertes\x27 => \x27office-quotation-studio.php\x27,";
if (str_contains($s, $line)) { echo "QUOTATION_ROUTE_ALREADY_PRESENT\n"; exit(0); }
$anchor="  \x27/office-test/crm/verkoopkansen\x27 => \x27office-crm-opportunities.php\x27,";
if (substr_count($s,$anchor)!==1) { fwrite(STDERR,"ACTIVE_ROUTER_ANCHOR_MISMATCH\n");exit(42); }
$new=str_replace($anchor,$anchor."\n".$line,$s);
$tmp=$p.".offertestudio-candidate-".getmypid();
if (file_put_contents($tmp,$new)===false) exit(43);
if (!rename($tmp,$p)) exit(44);
echo "QUOTATION_ROUTE_INSTALLED\n";
' "$router"
"$php_bin" -l "$router"
"$php_bin" -l "$root/public/office-quotation-studio.php"
"$php_bin" -l "$root/src/Quotation/OfficeQuotationComposer.php"
"$php_bin" -l "$root/src/Quotation/OfficeQuotationDraftRepository.php"
grep -Fq "'/office-test/crm/offertes'" "$router"
trap - ERR
echo QUOTATION_CODE_AND_ROUTER_INSTALLED
echo QUOTATION_SCHEMA_AND_FLAGS_NOT_CHANGED
