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
      expect(init).toMatchObject({ method: "GET", redirect: "error", headers: { Authorization: "Bearer secret-marker" } });
      expect(init.body).toBeUndefined();
      expect(init.signal).toBeInstanceOf(AbortSignal);
    }
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

  it("accepts an empty bank response", async () => {
    mockBank([], []);
    expect(await getMoneybirdBankData(env)).toEqual({ balances_available: false, period: "this_month", accounts: [], transactions: [] });
  });

  it("rejects malformed JSON", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("private-invalid-json")));
    await expect(getMoneybirdBankData(env)).rejects.toThrow("Invalid Moneybird bank response.");
  });
});
