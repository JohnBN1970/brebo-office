import { afterEach, describe, expect, it, vi } from "vitest";
import { getMoneybirdBankData } from "../src/moneybird-bank";

const env = { MONEYBIRD_ADMINISTRATION_ID: "999", MONEYBIRD_ACCESS_TOKEN: "secret-marker" } as Env;
const account = { id: "502980359672039351", type: "BankAccount", active: true, name: "Bank", identifier: "NL00TEST", currency: "EUR" };
const mutation = { id: "502980365165528473", financial_account_id: account.id, amount: "-12.3400", date: "2026-11-06", message: "Test" };

function mockBank(accounts: unknown = [account], mutations: unknown = [mutation]) {
  const mock = vi.fn().mockResolvedValueOnce(Response.json(accounts)).mockResolvedValueOnce(Response.json(mutations));
  vi.stubGlobal("fetch", mock);
  return mock;
}

describe("Moneybird bank-only evidence", () => {
  afterEach(() => vi.unstubAllGlobals());

  it("reads only the configured accounts and this month's mutations with GET", async () => {
    const fetchMock = mockBank();
    expect(await getMoneybirdBankData(env)).toEqual({
      balances_available: false, period: "this_month",
      accounts: [{ id: account.id, name: "Bank", identifier: "NL00TEST", currency: "EUR" }],
      transactions: [mutation],
    });
    expect(fetchMock.mock.calls.map(call => call[0])).toEqual([
      "https://moneybird.com/api/v2/999/financial_accounts.json",
      "https://moneybird.com/api/v2/999/financial_mutations.json?filter=period%3Athis_month%2Cstate%3Aall",
    ]);
    for (const [, init] of fetchMock.mock.calls) {
      expect(init).toMatchObject({ method: "GET", redirect: "manual", headers: { Authorization: "Bearer secret-marker" } });
      expect(init.body).toBeUndefined();
      expect(init.signal).toBeInstanceOf(AbortSignal);
    }
  });

  it("constructs outbound requests in the real Worker runtime without following redirects", async () => {
    const fetchMock = vi.fn().mockImplementation(async (url: string, init: RequestInit) => {
      const request = new Request(url, init);
      expect(request.redirect).toBe("manual");
      return Response.json([]);
    });
    vi.stubGlobal("fetch", fetchMock);
    expect(await getMoneybirdBankData(env)).toMatchObject({ accounts: [], transactions: [] });
    expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it("strips invoice/payment links and balances instead of importing or deriving them", async () => {
    mockBank([{ ...account, balance: "9000" }], [{ ...mutation, payments: [{ invoice_id: "private-invoice" }], amount_open: "0", state: "processed" }]);
    const result = JSON.stringify(await getMoneybirdBankData(env));
    expect(result).not.toMatch(/private-invoice|payments|amount_open|9000|processed/);
    expect(result).toContain('"balances_available":false');
  });

  it("excludes non-bank and inactive accounts and their transactions", async () => {
    mockBank([account, { ...account, id: "2", type: "CashAccount" }, { ...account, id: "3", active: false }], [mutation, { ...mutation, id: "4", financial_account_id: "2" }, { ...mutation, id: "5", financial_account_id: "3" }]);
    const bank = await getMoneybirdBankData(env);
    expect(bank.accounts).toHaveLength(1);
    expect(bank.transactions).toEqual([mutation]);
  });

  it.each([
    ["non-list accounts", {}, [mutation]],
    ["non-list transactions", [account], {}],
    ["numeric account id", [{ ...account, id: 123 }], []],
    ["duplicate accounts", [account, account], []],
    ["invalid currency", [{ ...account, currency: "?" }], []],
    ["missing active flag", [{ ...account, active: undefined }], []],
    ["string false active flag", [{ ...account, active: "false" }], []],
    ["string true active flag", [{ ...account, active: "true" }], []],
    ["numeric active flag", [{ ...account, active: 1 }], []],
    ["null active flag", [{ ...account, active: null }], []],
    ["numeric amount", [account], [{ ...mutation, amount: 12.34 }]],
    ["invalid amount", [account], [{ ...mutation, amount: "NaN" }]],
    ["invalid date", [account], [{ ...mutation, date: "2026-02-30" }]],
    ["duplicate transactions", [account], [mutation, mutation]],
    ["over 100 transactions", [account], Array.from({ length: 101 }, (_, i) => ({ ...mutation, id: String(i) }))],
  ])("rejects %s without a partial success", async (_name, accounts, mutations) => {
    mockBank(accounts, mutations);
    await expect(getMoneybirdBankData(env)).rejects.toThrow("Invalid Moneybird bank response.");
  });

  it.each([400, 401, 429, 500])("does not expose a provider error body for HTTP %s", async status => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("secret-marker private-account", { status })));
    await expect(getMoneybirdBankData(env)).rejects.toThrow("Moneybird request failed.");
  });

  it.each([301, 302, 307, 308])("rejects HTTP %s without forwarding credentials to a redirect", async status => {
    const fetchMock = vi.fn().mockResolvedValue(new Response("private-redirect-body", {
      status, headers: { Location: "https://other.example/private" },
    }));
    vi.stubGlobal("fetch", fetchMock);
    await expect(getMoneybirdBankData(env)).rejects.toThrow("Moneybird request failed.");
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock.mock.calls).toMatchObject([[expect.any(String), { redirect: "manual" }]]);
  });

  it("accepts an empty bank response", async () => {
    mockBank([], []);
    expect(await getMoneybirdBankData(env)).toEqual({ balances_available: false, period: "this_month", accounts: [], transactions: [] });
  });

  it("rejects malformed JSON", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("private-invalid-json")));
    await expect(getMoneybirdBankData(env)).rejects.toThrow("Invalid Moneybird bank response.");
  });
});

describe("Moneybird booked balances", () => {
  afterEach(() => vi.unstubAllGlobals());
  const balanceEnv = { ...env, MONEYBIRD_BANK_BOOKED_BALANCE_ENABLED: 'enabled' } as Env;
  function provider(report: unknown, status = 200) {
    const mock = vi.fn().mockImplementation(async (url: string, _init: RequestInit) => {
      if (url.includes('/financial_accounts.json')) return Response.json([account]);
      if (url.includes('/financial_mutations.json')) return Response.json([mutation]);
      return Response.json(report, { status });
    });
    vi.stubGlobal('fetch', mock);
    return mock;
  }
  const report = () => ({ total:'PRIVATE-MIXED-TOTAL', financial_accounts:[
    { financial_account_id:account.id, type:'BankAccount', balance:'12345678901234567890.1200', name:'PRIVATE-REPORT-NAME' },
    { financial_account_id:'999', type:'CashAccount', balance:'9000' },
  ] });
  it('reads the booked bank report separately and preserves decimal precision and provenance', async()=>{
    const mock=provider(report());
    const result=await getMoneybirdBankData(balanceEnv);
    expect(result.balances_available).toBe(false);
    expect(result.booked_balances).toMatchObject({source:'moneybird_bank_balance_report',period:'this_month',accounts:[{financial_account_id:account.id,amount:'12345678901234567890.1200'}]});
    expect(result.booked_balances?.retrieved_at).toMatch(/^\d{4}-\d{2}-\d{2}T/);
    expect(mock.mock.calls.map(call=>call[0])).toContain('https://moneybird.com/api/v2/999/reports/bank_balance.json?period=this_month');
    expect(mock.mock.calls).toHaveLength(3);
    expect(mock.mock.calls.every(([,init])=>init.method==='GET'&&init.redirect==='manual')).toBe(true);
    expect(JSON.stringify(result)).not.toMatch(/PRIVATE-MIXED-TOTAL|PRIVATE-REPORT-NAME|9000/);
  });
  it('does not invent a zero balance for an account omitted by Moneybird',async()=>{
    provider({financial_accounts:[]});
    expect((await getMoneybirdBankData(balanceEnv)).booked_balances?.accounts).toEqual([]);
  });
  it.each([
    { financial_accounts:[{financial_account_id:account.id,type:'BankAccount',balance:12.34}] },
    { financial_accounts:[{financial_account_id:account.id,type:'BankAccount',balance:'NaN'}] },
    { financial_accounts:[{financial_account_id:account.id,type:'BankAccount',balance:'1'},{financial_account_id:account.id,type:'BankAccount',balance:'2'}] },
    { financial_accounts:[{financial_account_id:account.id,type:'CashAccount',balance:'1'}] },
    { financial_accounts:{} },
  ])('keeps mutation reads working while rejecting malformed booked report %j',async value=>{
    provider(value);
    const result=await getMoneybirdBankData(balanceEnv);
    expect(result.transactions).toHaveLength(1);
    expect(result.booked_balances).toBeNull();
  });
  it('does not expose a failed report or lose existing bank evidence',async()=>{
    provider({private:'PRIVATE-PROVIDER-TOKEN'},403);
    const result=await getMoneybirdBankData(balanceEnv);
    expect(result.booked_balances).toBeNull();
    expect(result.accounts).toHaveLength(1);
    expect(JSON.stringify(result)).not.toContain('PRIVATE-PROVIDER-TOKEN');
  });
});
