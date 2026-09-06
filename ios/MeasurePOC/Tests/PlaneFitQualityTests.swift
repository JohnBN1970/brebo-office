import XCTest
@testable import BreboMeasurePOC

final class PlaneFitQualityTests: XCTestCase {
    func testPlaneFitNeedsSamplesAndLowResidual() {
        XCTAssertFalse(PlaneFitQuality(sampleCount: 49, residualMm: 2).isUsableForResearch)
        XCTAssertFalse(PlaneFitQuality(sampleCount: 100, residualMm: 11).isUsableForResearch)
        XCTAssertTrue(PlaneFitQuality(sampleCount: 100, residualMm: 2).isUsableForResearch)
    }
}
