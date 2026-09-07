import XCTest
@testable import BreboMeasurePOC

final class ScanBuildGateTests: XCTestCase {
    func testSigningDoesNotPrecedeHostedCompile() {
        XCTAssertEqual(ScanBuildGate.current, .awaitingHostedCompile)
    }
}
