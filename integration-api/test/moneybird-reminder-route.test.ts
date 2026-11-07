import { env } from 'cloudflare:test';
import { afterEach, describe, expect, it, vi } from 'vitest';
import core from '../src/index';
import { hmacSha256Hex, sha256Hex } from '../src/crypto';
const path='/v1/accounting/sales-reminders';
const secret='test-shared-secret-with-sufficient-length';
const configured={ ...env, MONEYBIRD_REMINDERS_ENABLED:'enabled', MONEYBIRD_ACCESS_TOKEN:'test-token', MONEYBIRD_ADMINISTRATION_ID:'999', BREBO_SHARED_SECRET:secret } as Env;
const command=()=>({ idempotency_key:'office-reminder-'+crypto.randomUUID(),reminder:{id:'123',contact_id:'4',workflow_id:'5',document_style_id:'6',identity_id:'7',email_address:'test@example.com',reminder_text:'Herinnering'}});
async function request(body:unknown, signed=true):Promise<Request> {
  const json=JSON.stringify(body), id=crypto.randomUUID(),timestamp=String(Math.floor(Date.now()/1000));
  const headers:Record<string,string>={'Content-Type':'application/json'};
  if(signed) Object.assign(headers,{'X-BREBO-Request-Id':id,'X-BREBO-Timestamp':timestamp,'X-BREBO-Signature':'v1='+await hmacSha256Hex(secret,['POST',path,await sha256Hex(json),timestamp,id].join('\n'))});
  return new Request('https://example.test'+path,{method:'POST',headers,body:json});
}
describe('Authenticated sales reminder route',()=>{
  afterEach(()=>vi.unstubAllGlobals());
  it('is disabled by default and authenticates before provider access',async()=>{
    const provider=vi.fn();vi.stubGlobal('fetch',provider);
    expect((await core.fetch(await request(command()),{...configured,MONEYBIRD_REMINDERS_ENABLED:undefined})).status).toBe(404);
    expect((await core.fetch(await request(command(),false),configured)).status).toBe(401);
    expect(provider).not.toHaveBeenCalled();
  });
  it('replays a confirmed command without another reminder',async()=>{
    const provider=vi.fn().mockResolvedValue(new Response('200',{status:200}));vi.stubGlobal('fetch',provider);const body=command();
    expect((await core.fetch(await request(body),configured)).status).toBe(200);
    const again=await core.fetch(await request(body),configured);expect(again.status).toBe(200);expect(await again.json()).toMatchObject({status:'ok',reminder:{invoice_id:'123'}});
    expect(provider).toHaveBeenCalledTimes(1);
  });
  it('locks timeout outcomes and never leaks provider data',async()=>{
    const provider=vi.fn().mockRejectedValue(new Error('SECRET_PROVIDER_MARKER'));vi.stubGlobal('fetch',provider);const body=command();
    const first=await core.fetch(await request(body),configured);expect(first.status).toBe(502);expect(await first.text()).not.toContain('SECRET_PROVIDER_MARKER');
    expect((await core.fetch(await request(body),configured)).status).toBe(409);expect(provider).toHaveBeenCalledTimes(1);
  });
});
