"""Exercise the workbench scripts under their declared CSP in a Chromium fixture."""
import html
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile

source = Path("office-independent/public/office-calc-crm-workbench.php").read_text()
policy = re.search(r'header\("Content-Security-Policy: ([^"]*script-src[^"]*)"\)', source)[1]
nonce = "fixtureNonce0123456789"
policy = policy.replace("{$scriptNonce}", nonce)
scripts = re.findall(r'<script[^>]*>.*?</script>', source, re.S)
assert len(scripts) == 2
scripts = [s.replace("""'.$scriptNonce.'""", nonce).replace("\\\\", "\\") for s in scripts]
rate_options = [line for line in source.splitlines() if "foreach($laborRates as $rate)" in line and "$rateOptions" in line]
assert len(rate_options) == 2 and all("data-hourly-rate" in line for line in rate_options)

fixture = """
<button data-calc-tab="data"></button><button data-calc-tab="hours"></button>
<div id="panel-data"></div><div id="panel-hours" hidden></div>
<div class="calc-collapsible-line"><button class="calc-line-toggle" aria-controls="detail" aria-expanded="false"></button></div><div id="detail" hidden></div>
<form id="calc-inline-new"></form><select form="calc-inline-new" name="labor_rate_code"><option></option><option data-hourly-rate="52.50">Glaszetter</option></select>
<input form="calc-inline-new" name="labor_hourly_rate"><input form="calc-inline-new" name="labor_norm"><input form="calc-inline-new" name="labor_hours"><input form="calc-inline-new" name="quantity" value="3"><input form="calc-inline-new" name="labor_driver">
<script>window.unapprovedScriptRan=true</script><output></output>
"""
checks = """
const find = selector => document.querySelector(selector);
find('[data-calc-tab="hours"]').click();
find('.calc-line-toggle').click();
const rate = find('select');
rate.selectedIndex = 1;
rate.dispatchEvent(new Event('change'));
const norm = find('[name=labor_norm]');
norm.value = '1.5';
norm.dispatchEvent(new Event('input'));
if (!window.unapprovedScriptRan && !find('#panel-hours').hidden && find('#panel-data').hidden
    && !find('#detail').hidden && find('[name=labor_hourly_rate]').value === '52.50'
    && find('[name=labor_hours]').value === '4.5') {
    find('output').textContent = 'PASS';
}
"""
browser = shutil.which("google-chrome") or shutil.which("chromium")
with tempfile.TemporaryDirectory() as tmp:
    page = Path(tmp) / "workbench.html"
    page.write_text('<meta http-equiv="Content-Security-Policy" content="' + html.escape(policy) + '">' +
                    fixture + "".join(scripts) + f'<script nonce="{nonce}">{checks}</script>')
    command = [browser, "--headless", "--disable-gpu", "--dump-dom", "--user-data-dir=" + tmp + "/profile", page.as_uri()]
    if os.getuid() == 0:
        command.insert(1, "--no-sandbox")
    result = subprocess.run(command, capture_output=True, text=True, timeout=30, check=True)
    assert "<output>PASS</output>" in result.stdout, "Workbench controls or script policy regression"
print("CALC_WORKBENCH_BROWSER=PASS")
