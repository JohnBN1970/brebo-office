import { describe, expect, it } from "vitest";
import { parseCalcPublication } from "../src/calc-publication";

const fixture = {
  office_version: "office-7", calc_version: "calc-11",
  commercial_summary: {
    purchase: 100, sales: 200, margin: 100, margin_pct: 50, vat: 30, total_incl_vat: 230,
    vat_rate: null,
    vat_breakdown: [
      { code: "z", label: "Z", rate: null, taxable_base: 100, vat_amount: 21, reverse_charged: false },
      { code: "a", label: "A", rate: 9, taxable_base: 100, vat_amount: 9, reverse_charged: false },
    ],
  },
};

describe("Office canonical Calc publication parity", () => {
  it("uses the PHP snapshot field order and VAT breakdown sort", () => {
    const publication = parseCalcPublication(fixture);
    const json = JSON.stringify({ contract: "brebo-calc-commercial-summary-v1", calculation_id: 41, ...publication });
    expect(json).toBe('{"contract":"brebo-calc-commercial-summary-v1","calculation_id":41,"office_version":"office-7","calc_version":"calc-11","commercial_summary":{"purchase":100,"sales":200,"margin":100,"margin_pct":50,"vat":30,"total_incl_vat":230,"vat_rate":null,"vat_breakdown":[{"code":"a","label":"A","rate":9,"taxable_base":100,"vat_amount":9,"reverse_charged":false},{"code":"z","label":"Z","rate":null,"taxable_base":100,"vat_amount":21,"reverse_charged":false}]}}');
  });
});
