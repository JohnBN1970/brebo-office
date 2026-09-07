import XCTest
@testable import BreboMeasurePOC

final class FieldValidationPolicyTests: XCTestCase {
    func testDefaultGateRequiresRepeatedEvidence() {
        let policy = FieldValidationPolicy()
        XCTAssertEqual(policy.requiredRepeatedScans, 10)
        XCTAssertEqual(policy.maximumMeanAbsoluteDeviationMm, 3)
        XCTAssertEqual(policy.maximumSpreadMm, 5)
    }
}
