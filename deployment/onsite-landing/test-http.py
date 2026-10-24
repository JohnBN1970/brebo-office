"""Isolated MySQL + HTTP checks; never uses production invitations or data."""
from pathlib import Path
import subprocess, time, urllib.request, urllib.error, json, hashlib, shutil, tempfile

source=Path(__file__).resolve().parent
test=Path(tempfile.mkdtemp(prefix='onsite-gate-test-'))
public=test/'public_html/onsite'
runtime=test/'onsite_landing/releases/fixture'
for p in [public,runtime,test/'onsite_landing/sessions',test/'office_calc_test/config']:p.mkdir(parents=True,exist_ok=True)
for f in ['index.php','style.css','gate.js','app.js','icon.svg']:shutil.copyfile(source/f,public/f)
for f in ['invitation.php','page.php']:shutil.copyfile(source/f,runtime/f)
(public/'.release').write_text('fixture')
(runtime/'OnSite-Android-test.apk').write_bytes(b'APK-FIXTURE-ONLY')
(runtime/'.purge-cache').write_text('fixture invalidation')
(test/'office_calc_test/config/config.php').write_text("""<?php return [
'OFFICE_BASE_PATH'=>'/office-test','OFFICE_PUBLIC_HOST'=>'brebobv.nl',
'OFFICE_ONSITE_ACTIVATION_ENABLED'=>'enabled','OFFICE_ONSITE_SCHEMA_VERIFIED'=>'enabled',
'OFFICE_INTAKE_PDO_DSN'=>'mysql:host=127.0.0.1;port=3306;dbname=u213420663_office_test;charset=utf8mb4',
'OFFICE_INTAKE_DB_USER'=>'root','OFFICE_INTAKE_DB_PASSWORD'=>'onsite-fixture-only'];
""")
setup=test/'setup.php'
setup.write_text("""<?php
$c=require __DIR__.'/office_calc_test/config/config.php';
$db=new PDO($c['OFFICE_INTAKE_PDO_DSN'],$c['OFFICE_INTAKE_DB_USER'],$c['OFFICE_INTAKE_DB_PASSWORD'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if(in_array('consume',$argv,true)){$db->exec("UPDATE office_onsite_activation_invitations SET consumed_at=UTC_TIMESTAMP()");exit;}
$db->exec('CREATE TABLE office_inzet_people (id BIGINT PRIMARY KEY,display_name VARCHAR(150),active TINYINT)');
$db->exec('CREATE TABLE office_onsite_activation_invitations (token_hash CHAR(64) PRIMARY KEY,person_id BIGINT,expires_at DATETIME,consumed_at DATETIME NULL)');
$db->exec("INSERT INTO office_inzet_people VALUES (1,'Fixture <script> name',1)");
$q=$db->prepare('INSERT INTO office_onsite_activation_invitations VALUES (?,1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),NULL)');
$q->execute([hash('sha256',str_repeat('a',43))]);
""")
subprocess.run(['php',str(setup)],check=True)
router=test/'router.php'
router.write_text("""<?php
$_SERVER['HTTPS']='on';$_SERVER['HTTP_HOST']='brebobv.nl';
$p=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($p==='/onsite/'||$p==='/onsite/index.php'){require __DIR__.'/public_html/onsite/index.php';return true;}
return false;
""")
log=open(test/'server.log','w')
server=subprocess.Popen(['php','-S','127.0.0.1:8896','-t',str(test/'public_html'),str(router)],stdout=log,stderr=log)
def request(path='',body=None,cookie='',origin='https://brebobv.nl'):
    headers={'Origin':origin,'Content-Type':'application/json'}
    if cookie:headers['Cookie']=cookie
    r=urllib.request.Request('http://127.0.0.1:8896/onsite/'+path,data=json.dumps(body).encode() if body is not None else None,headers=headers)
    try:response=urllib.request.urlopen(r)
    except urllib.error.HTTPError as e:response=e
    return response.status,response.headers,response.read()
def check(ok,label):
    assert ok,label
    print('PASS '+label,flush=True)
try:
    time.sleep(1)
    status,h,b=request()
    check(status==403 and b'Fixture' not in b and b'Download voor Android' not in b,'uninvited page reveals no employee or installation')
    check('no-store' in h['Cache-Control'],'responses cannot be cached')
    check(h.get('X-LiteSpeed-Purge')=='*' and h.get('X-LiteSpeed-Purge2')=='public,*','deployment invalidates retired public copies')
    check(request()[1].get('X-LiteSpeed-Purge') is None,'cache invalidation happens only once per deployment')
    check(request('?action=download')[0]==403,'direct download denied without invitation')
    check(request('?action=unlock',{'activation_token':'a'*43},origin='https://untrusted.example')[0]==403,'foreign origin rejected')
    check(request('?action=unlock',{'activation_token':'unknown'*7})[0]==403,'unknown token rejected')
    status,h,b=request('?action=unlock',{'activation_token':'a'*43,'person_id':999})
    cookie=h['Set-Cookie'].split(';')[0]
    check(status==200 and json.loads(b)['ok'],'valid fixture invitation unlocks page')
    check(all(flag in h['Set-Cookie'].lower() for flag in ['secure','httponly','samesite=strict','path=/onsite/']),'scoped secure session cookie')
    status,h,b=request(cookie=cookie)
    check(status==200 and b'Fixture &lt;script&gt; name' in b and b'WELKOM' in b,'server selects and escapes employee name')
    check(b'a'*43 not in b,'raw invitation is never rendered into HTML')
    check(request('?action=download',cookie=cookie)[2]==b'APK-FIXTURE-ONLY','authorized download succeeds without consuming invitation')
    check(request(cookie=cookie)[0]==200,'page remains usable after download')
    subprocess.run(['php',str(setup),'consume'],check=True)
    check(request('?action=download',cookie=cookie)[0]==403,'consumed invitation revokes existing download grant')
    check(request(cookie=cookie)[0]==403,'consumed invitation revokes page access')
finally:
    server.terminate();server.wait();log.close()
