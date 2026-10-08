import { describe, expect, it } from "vitest";
import { parseCalcPublication } from "../src/calc-publication";

const publication = {
  office_version: "office-1",
  calc_version: "calc-1",
  commercial_summary: {
    purchase: 100, sales: 150, margin: 50, margin_pct: 33.333,
    vat: 31.5, total_incl_vat: 181.5,
    vat_breakdown: [{ code: "high", label: "High", taxable_base: 150, vat_amount: 31.5, reverse_charged: false }],
  },
};

describe("standalone Calc publication contract", () => {
  it("accepts the commercial summary and preserves version binding", () => {
    const parsed = parseCalcPublication(publication);
    expect(parsed.office_version).toBe("office-1");
    expect(parsed.calc_version).toBe("calc-1");
    expect(parsed.commercial_summary.total_incl_vat).toBe(181.5);
  });
  it("rejects a mismatch between sales, VAT and total", () => {
    expect(() => parseCalcPublication({ ...publication, commercial_summary: { ...publication.commercial_summary, total_incl_vat: 180 } })).toThrow();
  });
  it("rejects a missing version", () => {
    expect(() => parseCalcPublication({ ...publication, office_version: "" })).toThrow();
  });
  it("rejects malformed VAT lines", () => {
    expect(() => parseCalcPublication({ ...publication, commercial_summary: { ...publication.commercial_summary, vat_breakdown: [{ code: "high" }] } })).toThrow();
  });
});
