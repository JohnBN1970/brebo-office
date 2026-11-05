import { afterEach, describe, expect, it, vi } from "vitest";
import { listBankMutations, MoneybirdResponseError } from "../src/moneybird";

describe("Moneybird bank mutations read-only", () => {
  afterEach(() => vi.unstubAllGlobals());

  const env = { MONEYBIRD_ADMINISTRATION_ID: "999", MONEYBIRD_ACCESS_TOKEN: "test-token" } as Env;

  it("reads transactions and exposes only whitelisted fields", async () => {
    const fetchMock = vi.fn().mockResolvedValue(Response.json([
      { id: "mut-1", financial_account_id: "bank-2", date: "2026-11-04", amount: "-35.50", message: "Supplier payment", state: "processed", private_field: "never-forward" }
    ]));
    vi.stubGlobal("fetch", fetchMock);
    const rows = await listBankMutations(env);
    expect(rows).toEqual([{ id: "mut-1", financial_account_id: "bank-2", date: "2026-11-04", amount: "-35.5000", description: "Supplier payment", state: "processed" }]);
    expect(JSON.stringify(rows)).not.toContain("never-forward");
    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0]!;
    expect(String(url)).toContain("/api/v2/999/financial_mutations.json");
    expect((init as RequestInit).method).toBe("GET");
  });

  it("rejects malformed responses without exposing tokens", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(Response.json({ error: "bad" })));
    await expect(listBankMutations(env)).rejects.toBeInstanceOf(MoneybirdResponseError);
  });

  it("fails closed when a page limit would truncate transactions", async () => {
    const fullPage = Array.from({length:100}, (_,i)=>({id:String(i),amount:"1.00"}));
    const fetchMock = vi.fn().mockResolvedValue(Response.json(fullPage));
    vi.stubGlobal("fetch", fetchMock);
    await expect(listBankMutations(env)).rejects.toThrow("page limit reached");
    expect(fetchMock).toHaveBeenCalledTimes(20);
  });
});
