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
        publishers_json TEXT NOT NULL
      )`);
    });
  }


  /** Internal-only ACL replacement. Call only after Office verifies authoritative ownership and actor grants. */
  async replace(record: CalculationAccessRecord): Promise<void> {
    const validId = (id: unknown): id is number => typeof id === "number" && Number.isSafeInteger(id) && id > 0;
    const validList = (ids: unknown): ids is number[] => Array.isArray(ids) && ids.every(validId) && new Set(ids).size === ids.length;
    if (!validId(record.calculation_id) || typeof record.active !== "boolean" || !validList(record.readers) || !validList(record.publishers)) {
      throw new TypeError("Invalid calculation access record");
    }
    this.ctx.storage.sql.exec(
      "INSERT INTO calculation_access (calculation_id, active, readers_json, publishers_json) VALUES (?, ?, ?, ?) ON CONFLICT(calculation_id) DO UPDATE SET active = excluded.active, readers_json = excluded.readers_json, publishers_json = excluded.publishers_json",
      record.calculation_id, record.active ? 1 : 0, JSON.stringify(record.readers), JSON.stringify(record.publishers),
    );
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
