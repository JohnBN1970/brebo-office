import { DurableObject } from "cloudflare:workers";

/** Immutable Calc snapshots scoped to a single Office calculation. */
export class CalcPublicationStore extends DurableObject<Env> {
  constructor(ctx: DurableObjectState, env: Env) {
    super(ctx, env);
    ctx.blockConcurrencyWhile(async () => {
      this.ctx.storage.sql.exec(`CREATE TABLE IF NOT EXISTS snapshots (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_hash TEXT NOT NULL UNIQUE,
        office_version TEXT NOT NULL,
        calc_version TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        published_by INTEGER NOT NULL,
        published_at INTEGER NOT NULL
      )`);
    });
  }

  async publish(contentHash: string, officeVersion: string, calcVersion: string, payloadJson: string, actorId: number): Promise<{ snapshot_id: number; content_hash: string; created: boolean; published_at: number }> {
    if (!/^[a-f0-9]{64}$/.test(contentHash) || !officeVersion || !calcVersion || !Number.isSafeInteger(actorId) || actorId <= 0) {
      throw new TypeError("Invalid snapshot publication metadata");
    }
    const existing = this.ctx.storage.sql.exec<{ id: number; published_at: number }>(
      "SELECT id, published_at FROM snapshots WHERE content_hash = ?", contentHash,
    ).toArray()[0];
    if (existing) return { snapshot_id: existing.id, content_hash: contentHash, created: false, published_at: existing.published_at };
    const publishedAt = Math.floor(Date.now() / 1000);
    const inserted = this.ctx.storage.sql.exec<{ id: number }>(
      "INSERT INTO snapshots (content_hash, office_version, calc_version, payload_json, published_by, published_at) VALUES (?, ?, ?, ?, ?, ?) RETURNING id",
      contentHash, officeVersion, calcVersion, payloadJson, actorId, publishedAt,
    ).toArray()[0];
    if (!inserted) throw new Error("Snapshot insertion failed");
    return { snapshot_id: inserted.id, content_hash: contentHash, created: true, published_at: publishedAt };
  }

  async latest(): Promise<{ snapshot_id: number; content_hash: string; published_by: number; published_at: number; payload: unknown } | null> {
    const row = this.ctx.storage.sql.exec<{ id: number; content_hash: string; published_by: number; published_at: number; payload_json: string }>(
      "SELECT id, content_hash, published_by, published_at, payload_json FROM snapshots ORDER BY published_at DESC, id DESC LIMIT 1",
    ).toArray()[0];
    if (!row) return null;
    return { snapshot_id: row.id, content_hash: row.content_hash, published_by: row.published_by, published_at: row.published_at, payload: JSON.parse(row.payload_json) };
  }
}
