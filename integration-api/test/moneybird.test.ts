import { afterEach, describe, expect, it, vi } from "vitest";
import { createAndSendSalesInvoice } from "../src/moneybird";

describe("Moneybird sales invoice dispatch", () => {
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("creates a draft and then sends exactly that Moneybird invoice", async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(Response.json({ id: "123", invoice_id: null, state: "draft", invoice_date: "2026-08-23", due_date: "2026-09-22", sent_at: null }, { status: 201 }))
      .mockResolvedValueOnce(Response.json({ id: "123", invoice_id: "2026-001", state: "open", invoice_date: "2026-08-23", due_date: "2026-09-22", sent_at: "2026-08-23T20:00:00Z" }));
    vi.stubGlobal("fetch", fetchMock);

    const result = await createAndSendSalesInvoice({ idempotency_key: "sales-invoice:test:0001", sales_invoice: { contact_id: "42", reference: "BREBO-TEST" }, sending: { delivery_method: "Email" } }, {
      MONEYBIRD_ADMINISTRATION_ID: "999",
      MONEYBIRD_ACCESS_TOKEN: "secret-token",
    } as Env);

    expect(fetchMock).toHaveBeenCalledTimes(2);
    const firstCall = fetchMock.mock.calls[0];
    const secondCall = fetchMock.mock.calls[1];
    expect(firstCall).toBeDefined();
    expect(secondCall).toBeDefined();
    expect(firstCall![0]).toBe("https://moneybird.com/api/v2/999/sales_invoices.json");
    expect(secondCall![0]).toBe("https://moneybird.com/api/v2/999/sales_invoices/123/send_invoice.json");
    expect(result).toMatchObject({ id: "123", invoice_id: "2026-001", state: "open" });
  });

  it("uses only the configured administration and bearer token", async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce(Response.json({ id: "abc", state: "draft" }, { status: 201 }))
      .mockResolvedValueOnce(Response.json({ id: "abc", invoice_id: "2026-002", state: "open" }));
    vi.stubGlobal("fetch", fetchMock);

    await createAndSendSalesInvoice({ idempotency_key: "sales-invoice:test:0002", sales_invoice: { contact_id: "7" } }, {
      MONEYBIRD_ADMINISTRATION_ID: "administration-123",
      MONEYBIRD_ACCESS_TOKEN: "token-that-must-not-leak",
    } as Env);

    for (const [url, init] of fetchMock.mock.calls) {
      expect(String(url)).toContain("/api/v2/administration-123/");
      const headers = (init as RequestInit).headers as Record<string, string>;
      expect(headers.Authorization).toBe("Bearer token-that-must-not-leak");
      expect(headers.Accept).toBe("application/json");
    }
  });

  it("does not include the bearer token in provider errors", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("forbidden token-that-must-not-leak", { status: 401 })));

    await expect(createAndSendSalesInvoice({ idempotency_key: "sales-invoice:test:0003", sales_invoice: { contact_id: "7" } }, {
      MONEYBIRD_ADMINISTRATION_ID: "administration-123",
      MONEYBIRD_ACCESS_TOKEN: "token-that-must-not-leak",
    } as Env)).rejects.not.toThrow(/token-that-must-not-leak/);
  });
});
