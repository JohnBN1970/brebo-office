import { DurableObject } from "cloudflare:workers";

export type SalesInvoiceDispatchState = "provider_pending" | "completed" | "reconciliation_required";

export interface SalesInvoiceDispatchRecord {
  state: SalesInvoiceDispatchState;
  response_json: string | null;
  updated_at: number;
}

export class SalesInvoiceDispatchGuard extends DurableObject<Env> {
  constructor(ctx: DurableObjectState, env: Env) {
    super(ctx, env);
    ctx.blockConcurrencyWhile(async () => {
      this.ctx.storage.sql.exec(`
        CREATE TABLE IF NOT EXISTS sales_invoice_dispatches (
          command_hash TEXT PRIMARY KEY,
          state TEXT NOT NULL,
          response_json TEXT,
          expires_at INTEGER NOT NULL,
          updated_at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS sales_invoice_dispatches_expiry ON sales_invoice_dispatches(expires_at);
      `);
    });
  }

  async begin(commandHash: string, expiresAt: number, now: number): Promise<{ accepted: boolean; record: SalesInvoiceDispatchRecord }> {
    this.ctx.storage.sql.exec("DELETE FROM sales_invoice_dispatches WHERE expires_at <= ?", now);
    const inserted = this.ctx.storage.sql.exec<{ command_hash: string }>(
      "INSERT OR IGNORE INTO sales_invoice_dispatches (command_hash, state, response_json, expires_at, updated_at) VALUES (?, 'provider_pending', NULL, ?, ?) RETURNING command_hash",
      commandHash,
      expiresAt,
      now,
    ).toArray();
    return { accepted: inserted.length === 1, record: this.get(commandHash) ?? { state: "provider_pending", response_json: null, updated_at: now } };
  }

  async complete(commandHash: string, responseJson: string, now: number): Promise<void> {
    this.ctx.storage.sql.exec(
      "UPDATE sales_invoice_dispatches SET state = 'completed', response_json = ?, updated_at = ? WHERE command_hash = ?",
      responseJson,
      now,
      commandHash,
    );
  }

  async requireReconciliation(commandHash: string, now: number): Promise<void> {
    this.ctx.storage.sql.exec(
      "UPDATE sales_invoice_dispatches SET state = 'reconciliation_required', updated_at = ? WHERE command_hash = ? AND state <> 'completed'",
      now,
      commandHash,
    );
  }

  private get(commandHash: string): SalesInvoiceDispatchRecord | null {
    const rows = this.ctx.storage.sql.exec<{ state: SalesInvoiceDispatchState; response_json: string | null; updated_at: number }>(
      "SELECT state, response_json, updated_at FROM sales_invoice_dispatches WHERE command_hash = ? LIMIT 1",
      commandHash,
    ).toArray();
    return rows[0] ?? null;
  }
}
