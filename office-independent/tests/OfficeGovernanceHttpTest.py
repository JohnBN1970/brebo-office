"""Exercise real PHP HTTP boundary using only a disposable native SQLite fixture."""
import os
import hashlib
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

root = Path(__file__).resolve().parents[2]
php = os.environ.get('GOVERNANCE_PHP', 'php')

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None

with tempfile.TemporaryDirectory(prefix='office-governance-http-') as tmp:
    tmp = Path(tmp)
    db = tmp / 'fixture.sqlite'
    env = dict(os.environ, GOVERNANCE_RENDER_FIXTURE=str(db))
    subprocess.run([php, str(root/'office-independent/tests/OfficeGovernanceIntegrationTest.php')], env=env, check=True, capture_output=True)
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        port = s.getsockname()[1]
    host = f'127.0.0.1:{port}'
    env.update(OFFICE_BASE_PATH='/office-test', OFFICE_TEST_GOVERNANCE_ENABLED='enabled',
               OFFICE_TEST_GOVERNANCE_SCHEMA_VERIFIED='enabled', OFFICE_CRM_SESSION_MODE='enabled',
               OFFICE_TEST_GOVERNANCE_CSRF_SECRET='fixture-secret-'*3,
               OFFICE_PUBLIC_HOST=host, OFFICE_INTAKE_PDO_DSN='sqlite:'+str(db))
    router = tmp/'router.php'
    target = str(root/'office-independent/public/office-governance.php').replace("'", "\\'")
    # TLS termination is simulated only inside this disposable localhost fixture.
    router.write_text("<?php $_SERVER['HTTPS']='on'; require '"+target+"';")
    log = open(tmp/'server.log', 'w')
    process = subprocess.Popen([php, '-S', host, str(router)], env=env, stdout=log, stderr=log)
    opener = urllib.request.build_opener(NoRedirect())
    def request(path, actor=None, data=None, origin=True, method=None):
        headers = {}
        if actor:
            headers['Cookie'] = 'office_test_session='+hashlib.sha256(actor.encode()).hexdigest()
        if data is not None and origin:
            headers['Origin'] = 'https://'+host
        encoded = urllib.parse.urlencode(data).encode() if data is not None else None
        req = urllib.request.Request('http://'+host+path, data=encoded, headers=headers, method=method)
        try:
            response = opener.open(req, timeout=5)
        except urllib.error.HTTPError as e:
            response = e
        return response.status, response.read().decode()
    try:
        for attempt in range(50):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1): break
            except OSError:
                time.sleep(.1)
        else:
            raise AssertionError('fixture server failed to start')
        status, _ = request('/office-test/risicos')
        assert status == 302, status
        for path in ['kwaliteit', 'risicos', 'inkoop']:
            status, html = request('/office-test/'+path+'?project_id=10&building_id=100', 'one')
            assert status == 200 and '<h1>' in html, (path,status,html)
        status, html = request('/office-test/kwaliteit?project_id=10', 'one')
        assert status == 200 and 'building_id=100' in html, (status,html)
        status, html = request('/office-test/kwaliteit?project_id=10&building_id=100', 'reader')
        assert status == 200 and '<form' not in html
        assert request('/office-test/risicos?project_id=10&building_id=200','one')[0] == 403
        assert request('/office-test/risicos?project_id=10&building_id=100','outsider')[0] == 404
        assert request('/office-test/risicos?project_id[]=10','one')[0] == 422
        assert request('/office-test/risicos','one',method='DELETE')[0] == 405
        status, html = request('/office-test/risicos?project_id=10&building_id=100','one')
        csrf = re.search(r'name="csrf" value="([^"]+)"',html).group(1)
        data = dict(project_id='10',building_id='100',id='0',revision='0',command='6'*32,
                    title='<script>alert(1)</script>',owner='one',status='open',due_date='2026-11-30',
                    cause='Oorzaak',event='Gebeurtenis',consequence='Gevolg',probability='2',impact='3',
                    measure='Maatregel',residual_risk='Restrisico',csrf=csrf)
        assert request('/office-test/risicos','one',data,origin=False)[0] == 403
        assert request('/office-test/risicos','one',dict(data,csrf='invalid'))[0] == 403
        assert request('/office-test/risicos','one',data)[0] == 303
        status, html = request('/office-test/risicos?project_id=10&building_id=100','one')
        assert status == 200 and '&lt;script&gt;' in html and '<script>alert(1)</script>' not in html
        print('PASS native HTTP: three pages, project links, sessions, ACL, Origin, CSRF, PRG and escaped content')
    finally:
        process.terminate()
        process.wait(timeout=5)
        log.close()
