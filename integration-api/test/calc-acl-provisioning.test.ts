import { describe, expect, it, vi } from "vitest";
import { syncCalculationAccess } from "../src/calc-acl-provisioning";

describe("trusted Office calculation ACL synchronization", () => {
  it("persists only authoritative Office permissions", async () => {
    const replace = vi.fn().mockResolvedValue(3);
    const resolve = vi.fn().mockResolvedValue({ calculation_id: 41, active: true, readers: [7], publishers: [5] });
    await expect(syncCalculationAccess(41, 2, { resolve }, { replace })).resolves.toBe(3);
    expect(resolve).toHaveBeenCalledWith(41);
    expect(replace).toHaveBeenCalledWith({ calculation_id: 41, active: true, readers: [7], publishers: [5] }, 2);
  });
  it("rejects missing or mismatched Office authority without writes", async () => {
    const replace = vi.fn();
    await expect(syncCalculationAccess(41, null, { resolve: async () => ({ calculation_id: 42, active: true, readers: [], publishers: [] }) }, { replace })).rejects.toThrow();
    expect(replace).not.toHaveBeenCalled();
  });
  it("rejects malformed or duplicated identities without writes", async () => {
    const replace = vi.fn();
    await expect(syncCalculationAccess(41, null, { resolve: async () => ({ calculation_id: 41, active: true, readers: [5, 5], publishers: [] }) }, { replace })).rejects.toThrow();
    expect(replace).not.toHaveBeenCalled();
  });
});
