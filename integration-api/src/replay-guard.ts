import { DurableObject } from "cloudflare:workers";

export class ReplayGuard extends DurableObject<Env> {
  constructor(ctx: DurableObjectState, env: Env) {
    super(ctx, env);
    ctx.blockConcurrencyWhile(async () => {
      this.ctx.storage.sql.exec(`
        CREATE TABLE IF NOT EXISTS request_ids (
          request_hash TEXT PRIMARY KEY,
          expires_at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS request_ids_expiry ON request_ids(expires_at);
      `);
    });
  }

  async useOnce(requestHash: string, expiresAt: number, now: number): Promise<boolean> {
    this.ctx.storage.sql.exec("DELETE FROM request_ids WHERE expires_at <= ?", now);
    const inserted = this.ctx.storage.sql.exec<{ request_hash: string }>(
      "INSERT OR IGNORE INTO request_ids (request_hash, expires_at) VALUES (?, ?) RETURNING request_hash",
      requestHash,
      expiresAt,
    ).toArray();
    return inserted.length === 1;
  }
}
