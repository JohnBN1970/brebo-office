import { describe, expect, it } from "vitest";
import { OfficeCalculationAccess, type CalculationAccessRecord } from "../src/calc-publication-access";

const record: CalculationAccessRecord = { calculation_id: 41, active: true, readers: [7], publishers: [5] };
const service = (value: CalculationAccessRecord | null) => new OfficeCalculationAccess({ find: async () => value });

describe("Office-native calculation access policy", () => {
  it("permits assigned publishers to publish and read", async () => {
    expect(await service(record).canPublish(41, 5)).toBe(true);
    expect(await service(record).canRead(41, 5)).toBe(true);
  });
  it("permits readers only to read", async () => {
    expect(await service(record).canRead(41, 7)).toBe(true);
    expect(await service(record).canPublish(41, 7)).toBe(false);
  });
  it("denies unknown actors and calculations", async () => {
    expect(await service(record).canRead(41, 8)).toBe(false);
    expect(await service(record).canPublish(42, 5)).toBe(false);
    expect(await service(null).canPublish(41, 5)).toBe(false);
  });
  it("rejects malformed authorization lists rather than trusting coerced IDs", async () => {
    expect(await service({ ...record, publishers: ["5"] as unknown as number[] }).canPublish(41, 5)).toBe(false);
    expect(await service({ ...record, readers: [0] }).canRead(41, 7)).toBe(false);
  });
  it("denies inactive records and invalid identities", async () => {
    expect(await service({ ...record, active: false }).canRead(41, 5)).toBe(false);
    expect(await service(record).canRead(41, 0)).toBe(false);
  });
});
