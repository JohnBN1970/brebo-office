#!/usr/bin/env python3
"""Fail closed if a development event can reach a remote system."""
from pathlib import Path
import re
import yaml

ROOT = Path(__file__).resolve().parents[2]
CENTRAL = 'office-test-all-modules-auto.yml'
SCHEDULED_OPERATIONS = {
    'finance-moneybird-receivables-sync.yml',
    'office-v3-runtime-readonly-audit.yml',
    'office-test-active-release-integrity.yml',
}

def check(root=ROOT):
    errors = []
    paths = list((root / '.github/workflows').glob('*.y*ml'))
    for path in paths:
        source = path.read_text()
        workflow = yaml.safe_load(source)
        events = workflow.get('on', workflow.get(True, {})) or {}
        if not isinstance(events, dict):
            events = dict.fromkeys(events if isinstance(events, list) else [events])
        remote = bool(re.search(r'secrets\.(?:DEPLOY_|CLOUDFLARE_)|\bssh\s|\bscp\s|npm run deploy|appleboy/', source))
        if not remote:
            continue
        if path.name != CENTRAL and set(events) & {'push', 'pull_request', 'pull_request_target', 'workflow_run', 'repository_dispatch'}:
            errors.append(f'{path.name}: automatic remote route outside central Office-test pipeline')
        if 'schedule' in events and path.name not in SCHEDULED_OPERATIONS:
            errors.append(f'{path.name}: unreviewed scheduled remote operation')
    production = yaml.safe_load((root / '.github/workflows/sboffice-deploy-develop.yml').read_text())
    events = production.get('on', production.get(True, {}))
    if set(events) != {'workflow_dispatch'}:
        errors.append('Production must only support workflow_dispatch')
    job = production['jobs']['deploy']
    if job.get('environment') != 'production' or "refs/tags/release-" not in job.get('if', '') or 'RELEASE-PRODUCTION' not in job.get('if', ''):
        errors.append('Production requires protected environment, release tag and explicit confirmation')
    if errors:
        raise SystemExit('\n'.join(errors))
    print(f'DEPLOYMENT_POLICY_PASS workflows={len(paths)}')

if __name__ == '__main__':
    check()
