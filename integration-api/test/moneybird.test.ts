import { describe, expect, it, vi } from "vitest";
import { createAndSendSalesInvoice } from "../src/moneybird";

describe("Moneybird sales invoice dispatch", () => {
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
    expect(fetchMock.mock.calls[0][0]).toBe("https://moneybird.com/api/v2/999/sales_invoices.json");
    expect(fetchMock.mock.calls[1][0]).toBe("https://moneybird.com/api/v2/999/sales_invoices/123/send_invoice.json");
    expect(result).toMatchObject({ id: "123", invoice_id: "2026-001", state: "open" });
  });
});
