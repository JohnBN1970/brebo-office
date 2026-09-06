import XCTest
@testable import BreboMeasurePOC

final class GeometryResultPolicyTests: XCTestCase {
    func testScannerOutputIsDetectedAndNotSilentlySelected() {
        XCTAssertEqual(GeometryResultPolicy.provenanceForScannerOutput(), .detected)
        XCTAssertFalse(GeometryResultPolicy.mayBecomeSelectedWithoutHumanReview)
    }
}
