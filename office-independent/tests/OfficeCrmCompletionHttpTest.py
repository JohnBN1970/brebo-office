"""Real PHP requests with disposable native CRM data; never connects to a remote service."""
import hashlib
import os
import re
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

root=Path(__file__).resolve().parents[2]
php=os.environ.get('CRM_TEST_PHP','php')
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args): return None
with tempfile.TemporaryDirectory(prefix='crm-http-') as temp:
    temp=Path(temp);db=temp/'fixture.sqlite'
    env=dict(os.environ,CRM_HTTP_FIXTURE=str(db))
    subprocess.run([php,str(root/'office-independent/tests/OfficeCrmCompletionIntegrationTest.php')],env=env,check=True,capture_output=True)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
    host=f'127.0.0.1:{port}'
    env.update(OFFICE_BASE_PATH='/office-test',OFFICE_TEST_CRM_ENABLED='enabled',OFFICE_CRM_AUTH_MODE='session_only',OFFICE_CRM_OPPORTUNITIES_SCHEMA_VERIFIED='enabled',OFFICE_CRM_OPPORTUNITIES_WORKSPACE_SCHEMA_VERIFIED='enabled',OFFICE_CRM_OPPORTUNITIES_WRITE_ENABLED='enabled',OFFICE_PUBLIC_HOST=host,OFFICE_INTAKE_PDO_DSN='sqlite:'+str(db))
    routes={'/office-test/marketing/leads':'office-crm-opportunities.php','/office-test/marketing/leads/nieuw':'office-crm-opportunity-create.php','/office-test/crm/verkoopkansen':'office-crm-opportunities.php','/office-test/crm/verkoopkansen/nieuw':'office-crm-opportunity-create.php','/office-test/crm/verkoopkansen/detail':'office-crm-opportunity-detail.php','/office-test/crm/verkoopkansen/fase':'office-crm-opportunity-transition.php','/office-test/crm/verkoopkansen/calculatie-context':'office-crm-calculation-context.php'}
    mapping=','.join("'"+path+"'=>'"+str(root/'office-independent/public'/filename)+"'" for path,filename in routes.items())
    router=temp/'router.php';router.write_text("<?php $_SERVER['HTTPS']='on'; $routes=["+mapping+"]; $p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH); if(!isset($routes[$p])){http_response_code(404);exit;} require $routes[$p];")
    log=open(temp/'server.log','w');process=subprocess.Popen([php,'-d','session.save_path='+str(temp),'-S',host,str(router)],env=env,stdout=log,stderr=log)
    opener=urllib.request.build_opener(NoRedirect());cookies={}
    def request(path,actor=None,data=None,origin=True,method=None,form_cookie=None):
        headers={}
        if actor:
            cookie='office_test_session='+hashlib.sha256(actor.encode()).hexdigest()
            form=form_cookie if form_cookie is not None else cookies.get(actor)
            if form: cookie+='; '+form
            headers['Cookie']=cookie
        if data is not None and origin: headers['Origin']='https://'+host
        req=urllib.request.Request('http://'+host+path,data=urllib.parse.urlencode(data).encode() if data is not None else None,headers=headers,method=method)
        try: response=opener.open(req,timeout=5)
        except urllib.error.HTTPError as error: response=error
        for value in response.headers.get_all('Set-Cookie') or []:
            if actor and value.startswith('BREBO_CRM_OPPORTUNITY_FORM='): cookies[actor]=value.split(';')[0]
        return response.status,response.read().decode(),response.headers
    def hidden(html,key): return re.search(r'name="'+key+r'" value="([^"]*)"',html).group(1)
    try:
        for attempt in range(50):
            try:
                with socket.create_connection(('127.0.0.1',port),timeout=.1): break
            except OSError: time.sleep(.1)
        else: raise AssertionError('PHP fixture failed to start')
        assert request('/office-test/crm/verkoopkansen')[0]==302
        assert request('/office-test/crm/verkoopkansen','inactive')[0]==302
        result=request('/office-test/crm/verkoopkansen/nieuw','reader');assert result[0]==403,result[:2]
        for view in ['kanban','list','calendar']:
            status,html,_=request('/office-test/crm/verkoopkansen?view='+view,'reader')
            assert status==200 and 'Nieuwe verkoopkans</a>' not in html,(status,html)
        status,html,_=request('/office-test/marketing/leads/nieuw','editor');assert status==200
        data=dict(csrf=hidden(html,'csrf'),request_key=hidden(html,'request_key'),title='<script>alert(1)</script>',description='Adviesvraag',source='Telefoon')
        assert request('/office-test/marketing/leads/nieuw','editor',data,origin=False)[0]==403
        assert request('/office-test/marketing/leads/nieuw','editor',dict(data,csrf='wrong'))[0]==403
        status,_,headers=request('/office-test/marketing/leads/nieuw','editor',data);assert status==303,status
        detail=headers['Location'];lead=int(urllib.parse.parse_qs(urllib.parse.urlparse(detail).query)['id'][0])
        status,html,_=request(detail,'editor');assert status==200 and '&lt;script&gt;' in html and '<script>alert(1)</script>' not in html
        key=hidden(html,'request_key');csrf=hidden(html,'csrf');revision=hidden(html,'revision')
        qualify=dict(csrf=csrf,request_key=key,revision=revision,action='qualify',relation_id='1',contact_id='1',title='Gekwalificeerde adviesvraag',description='Inspectie',service_type='advice',expected_value='300,00',expected_close_date='2026-11-30')
        assert request(detail,'reader',qualify)[0]==403
        assert request(detail,'editor',qualify)[0]==303
        status,html,_=request(detail,'editor');assert 'Prospect' in html and 'Bestaande relatie' in html
        stale=dict(qualify,request_key=os.urandom(16).hex(),action='update');stale_response=request(detail,'editor',stale);assert stale_response[0]==409,stale_response[:2]
        context=request('/office-test/crm/verkoopkansen/calculatie-context?id='+str(lead),'editor')
        assert context[0]==200 and 'crm_opportunity' in context[1] and 'project_id' not in context[1]
        assert request('/office-test/crm/verkoopkansen/calculatie-context?id='+str(lead),'reader')[0]==403
        status,html,_=request(detail,'editor');assert status==200
        activity=dict(csrf=hidden(html,'csrf'),request_key=os.urandom(16).hex(),revision=hidden(html,'revision'),action='activity',activity_type='opname',opname_type='measurement',title='Inmeting plannen',scheduled_at='2026-11-18T10:15')
        assert request(detail,'editor',activity)[0]==303
        status,calendar,_=request('/office-test/crm/verkoopkansen?view=calendar&month=2026-11','editor')
        assert status==200 and 'Inmeting plannen' in calendar
        # A stolen form cookie and token do not authorize another Office login.
        stolen=cookies['editor'];assert request(detail,'reader',dict(activity,request_key='a'*32),form_cookie=stolen)[0]==403
        assert request('/office-test/crm/verkoopkansen','editor',method='DELETE')[0]==405
        print('CRM_COMPLETION_HTTP_OK: native sessions, reader/editor, qualification, details, calendar, CSRF/Origin, revisions and pre-project Calc context')
    finally:
        process.terminate();process.wait(timeout=5);log.close()
        if os.environ.get('CRM_HTTP_DEBUG'): print((temp/'server.log').read_text())
