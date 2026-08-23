import { env } from "cloudflare:test";
import { describe, expect, it } from "vitest";

describe("Sales invoice dispatch security guard", () => {
  it("accepts a command once and exposes provider_pending on concurrent/repeated begin", async () => {
    const guard = env.SALES_INVOICE_DISPATCH_GUARD.getByName(`security-${crypto.randomUUID()}`);
    const hash = `command-${crypto.randomUUID()}`;
    const now = 1_000;

    const first = await guard.begin(hash, now + 3_600, now);
    expect(first.accepted).toBe(true);
    expect(first.record).toMatchObject({ state: "provider_pending", response_json: null });

    const second = await guard.begin(hash, now + 3_600, now + 1);
    expect(second.accepted).toBe(false);
    expect(second.record.state).toBe("provider_pending");
  });

  it("replays a completed response without creating a new provider attempt", async () => {
    const guard = env.SALES_INVOICE_DISPATCH_GUARD.getByName(`security-${crypto.randomUUID()}`);
    const hash = `command-${crypto.randomUUID()}`;
    const now = 2_000;
    const response = JSON.stringify({ status: "ok", sales_invoice: { id: "mb-123" } });

    expect((await guard.begin(hash, now + 3_600, now)).accepted).toBe(true);
    await guard.complete(hash, response, now + 1);

    const repeated = await guard.begin(hash, now + 3_600, now + 2);
    expect(repeated.accepted).toBe(false);
    expect(repeated.record.state).toBe("completed");
    expect(repeated.record.response_json).toBe(response);
  });

  it("locks uncertain provider outcomes for reconciliation instead of retry", async () => {
    const guard = env.SALES_INVOICE_DISPATCH_GUARD.getByName(`security-${crypto.randomUUID()}`);
    const hash = `command-${crypto.randomUUID()}`;
    const now = 3_000;

    expect((await guard.begin(hash, now + 3_600, now)).accepted).toBe(true);
    await guard.requireReconciliation(hash, now + 1);

    const repeated = await guard.begin(hash, now + 3_600, now + 2);
    expect(repeated.accepted).toBe(false);
    expect(repeated.record.state).toBe("reconciliation_required");
    expect(repeated.record.response_json).toBeNull();
  });

  it("never downgrades a completed command to reconciliation_required", async () => {
    const guard = env.SALES_INVOICE_DISPATCH_GUARD.getByName(`security-${crypto.randomUUID()}`);
    const hash = `command-${crypto.randomUUID()}`;
    const now = 4_000;
    const response = JSON.stringify({ status: "ok", sales_invoice: { id: "mb-456" } });

    await guard.begin(hash, now + 3_600, now);
    await guard.complete(hash, response, now + 1);
    await guard.requireReconciliation(hash, now + 2);

    const repeated = await guard.begin(hash, now + 3_600, now + 3);
    expect(repeated.record.state).toBe("completed");
    expect(repeated.record.response_json).toBe(response);
  });
});
