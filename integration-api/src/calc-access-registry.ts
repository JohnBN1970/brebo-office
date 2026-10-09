import { DurableObject } from "cloudflare:workers";
import type { CalculationAccessRecord, CalculationAccessSource } from "./calc-publication-access";

/** Private, per-calculation Office ACL storage. No public mutation route is exposed. */
export class CalcAccessRegistry extends DurableObject<Env> implements CalculationAccessSource {
  constructor(ctx: DurableObjectState, env: Env) {
    super(ctx, env);
    ctx.blockConcurrencyWhile(async () => {
      this.ctx.storage.sql.exec(`CREATE TABLE IF NOT EXISTS calculation_access (
        calculation_id INTEGER PRIMARY KEY,
        active INTEGER NOT NULL CHECK (active IN (0, 1)),
        readers_json TEXT NOT NULL,
        publishers_json TEXT NOT NULL,
        revision INTEGER NOT NULL DEFAULT 1
      )`);
    });
  }


  /** Internal-only ACL replacement. Call only after Office verifies authoritative ownership and actor grants. */
  async replace(record: CalculationAccessRecord, expectedRevision: number | null): Promise<number> {
    const validId = (id: unknown): id is number => typeof id === "number" && Number.isSafeInteger(id) && id > 0;
    const validList = (ids: unknown): ids is number[] => Array.isArray(ids) && ids.every(validId) && new Set(ids).size === ids.length;
    if (!validId(record.calculation_id) || typeof record.active !== "boolean" || !validList(record.readers) || !validList(record.publishers)) {
      throw new TypeError("Invalid calculation access record");
    }
    if (expectedRevision === null) {
      const created = this.ctx.storage.sql.exec<{ revision: number }>(
        "INSERT INTO calculation_access (calculation_id, active, readers_json, publishers_json, revision) VALUES (?, ?, ?, ?, 1) ON CONFLICT(calculation_id) DO NOTHING RETURNING revision",
        record.calculation_id, record.active ? 1 : 0, JSON.stringify(record.readers), JSON.stringify(record.publishers),
      ).toArray()[0];
      if (!created) throw new Error("Calculation access revision conflict");
      return created.revision;
    }
    if (!Number.isSafeInteger(expectedRevision) || expectedRevision <= 0) throw new TypeError("Invalid expected ACL revision");
    const updated = this.ctx.storage.sql.exec<{ revision: number }>(
      "UPDATE calculation_access SET active = ?, readers_json = ?, publishers_json = ?, revision = revision + 1 WHERE calculation_id = ? AND revision = ? RETURNING revision",
      record.active ? 1 : 0, JSON.stringify(record.readers), JSON.stringify(record.publishers), record.calculation_id, expectedRevision,
    ).toArray()[0];
    if (!updated) throw new Error("Calculation access revision conflict");
    return updated.revision;
  }

  async find(calculationId: number): Promise<CalculationAccessRecord | null> {
    if (!Number.isSafeInteger(calculationId) || calculationId <= 0) return null;
    const row = this.ctx.storage.sql.exec<{ calculation_id: number; active: number; readers_json: string; publishers_json: string }>(
      "SELECT calculation_id, active, readers_json, publishers_json FROM calculation_access WHERE calculation_id = ?", calculationId,
    ).toArray()[0];
    if (!row) return null;
    return { calculation_id: row.calculation_id, active: row.active === 1, readers: JSON.parse(row.readers_json), publishers: JSON.parse(row.publishers_json) };
  }
}
