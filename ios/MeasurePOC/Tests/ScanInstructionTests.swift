import XCTest
@testable import BreboMeasurePOC

final class ScanInstructionTests: XCTestCase {
    func testPrimaryInstructionsDoNotRequireEndpointSelection() {
        let instructions = ScanInstruction.steps.joined(separator: " ").lowercased()
        XCTAssertFalse(instructions.contains("meetpunt"))
        XCTAssertFalse(instructions.contains("aantikken"))
    }
}
