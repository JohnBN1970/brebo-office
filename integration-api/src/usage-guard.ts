import { DurableObject } from "cloudflare:workers";

export type UsageDecision = "accepted" | "rate_limited" | "budget_exhausted";

export class UsageGuard extends DurableObject<Env> {
  constructor(ctx: DurableObjectState, env: Env) {
    super(ctx, env);
    ctx.blockConcurrencyWhile(async () => {
      this.ctx.storage.sql.exec(`
        CREATE TABLE IF NOT EXISTS rate_windows (
          window_start INTEGER PRIMARY KEY,
          request_count INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS monthly_budgets (
          period TEXT PRIMARY KEY,
          reserved_tokens INTEGER NOT NULL
        );
      `);
    });
  }

  async reserve(
    now: number,
    rateWindowSeconds: number,
    maxRequestsPerWindow: number,
    period: string,
    estimatedTokens: number,
    monthlyTokenBudget: number,
  ): Promise<UsageDecision> {
    const windowStart = now - (now % rateWindowSeconds);
    this.ctx.storage.sql.exec("DELETE FROM rate_windows WHERE window_start < ?", windowStart);
    const currentRate = this.ctx.storage.sql
      .exec<{ request_count: number }>(
        "SELECT request_count FROM rate_windows WHERE window_start = ?",
        windowStart,
      )
      .toArray()[0]?.request_count ?? 0;

    if (currentRate >= maxRequestsPerWindow) return "rate_limited";

    this.ctx.storage.sql.exec("DELETE FROM monthly_budgets WHERE period <> ?", period);
    const reserved = this.ctx.storage.sql
      .exec<{ reserved_tokens: number }>(
        "SELECT reserved_tokens FROM monthly_budgets WHERE period = ?",
        period,
      )
      .toArray()[0]?.reserved_tokens ?? 0;

    if (reserved + estimatedTokens > monthlyTokenBudget) return "budget_exhausted";

    this.ctx.storage.sql.exec(
      `INSERT INTO rate_windows (window_start, request_count) VALUES (?, 1)
       ON CONFLICT(window_start) DO UPDATE SET request_count = request_count + 1`,
      windowStart,
    );
    this.ctx.storage.sql.exec(
      `INSERT INTO monthly_budgets (period, reserved_tokens) VALUES (?, ?)
       ON CONFLICT(period) DO UPDATE SET reserved_tokens = reserved_tokens + excluded.reserved_tokens`,
      period,
      estimatedTokens,
    );
    return "accepted";
  }
}
