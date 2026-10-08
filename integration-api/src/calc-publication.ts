/** Standalone Calc publication contract; no Drupal runtime dependencies. */
export type CalcCommercialSummary = {
  purchase: number;
  sales: number;
  margin: number;
  margin_pct: number;
  vat: number;
  total_incl_vat: number;
  vat_rate?: number | null;
  vat_breakdown?: Array<{
    code: string;
    label: string;
    rate?: number | null;
    taxable_base: number;
    vat_amount: number;
    reverse_charged: boolean;
  }>;
};

export type CalcPublication = {
  office_version: string;
  calc_version: string;
  commercial_summary: CalcCommercialSummary;
};

const amounts = ["purchase", "sales", "margin", "margin_pct", "vat", "total_incl_vat"] as const;

export function parseCalcPublication(value: unknown): CalcPublication {
  if (!isRecord(value) || !nonempty(value.office_version) || !nonempty(value.calc_version) || !isRecord(value.commercial_summary)) {
    throw new TypeError("Invalid Calc publication version or commercial summary");
  }
  const summary = value.commercial_summary;
  for (const field of amounts) {
    if (!finiteNumber(summary[field])) throw new TypeError(`Invalid commercial_summary.${field}`);
  }
  if (Math.abs((summary.total_incl_vat as number) - (summary.sales as number) - (summary.vat as number)) > 0.01) {
    throw new TypeError("commercial_summary.total_incl_vat must equal sales + vat");
  }
  if (summary.vat_rate !== undefined && summary.vat_rate !== null && !finiteNumber(summary.vat_rate)) {
    throw new TypeError("Invalid commercial_summary.vat_rate");
  }
  const breakdown = summary.vat_breakdown ?? [];
  if (!Array.isArray(breakdown) || breakdown.some((item: unknown) => !isRecord(item) || !nonempty(item.code) || !nonempty(item.label) || !finiteNumber(item.taxable_base) || !finiteNumber(item.vat_amount) || typeof item.reverse_charged !== "boolean" || (item.rate != null && !finiteNumber(item.rate)))) {
    throw new TypeError("Invalid commercial_summary.vat_breakdown");
  }
  return {
    office_version: (value.office_version as string).trim(),
    calc_version: (value.calc_version as string).trim(),
    commercial_summary: {
      purchase: summary.purchase as number,
      sales: summary.sales as number,
      margin: summary.margin as number,
      margin_pct: summary.margin_pct as number,
      vat: summary.vat as number,
      total_incl_vat: summary.total_incl_vat as number,
      vat_rate: (summary.vat_rate as number | null | undefined) ?? null,
      vat_breakdown: breakdown.map((item: Record<string, unknown>) => ({
        code: (item.code as string).trim(), label: (item.label as string).trim(),
        rate: (item.rate as number | null | undefined) ?? null,
        taxable_base: item.taxable_base as number, vat_amount: item.vat_amount as number,
        reverse_charged: item.reverse_charged as boolean,
      })).sort((a, b) => compareOrdinal(a.code, b.code) || compareOrdinal(a.label, b.label)),
    },
  };
}

function isRecord(value: unknown): value is Record<string, unknown> { return value !== null && typeof value === "object" && !Array.isArray(value); }
function nonempty(value: unknown): value is string { return typeof value === "string" && value.trim().length > 0; }
function finiteNumber(value: unknown): value is number { return typeof value === "number" && Number.isFinite(value); }

// PHP usort compares these strings lexicographically, not with locale collation.
function compareOrdinal(a: string, b: string): number { return a < b ? -1 : a > b ? 1 : 0; }
