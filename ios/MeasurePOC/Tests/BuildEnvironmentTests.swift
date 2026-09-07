import XCTest
@testable import BreboMeasurePOC

final class BuildEnvironmentTests: XCTestCase {
    func testCloudCompilesButPhoneProvesSensorBehaviour() {
        XCTAssertEqual(BuildEnvironment.compileGate, .hostedMacOS)
        XCTAssertEqual(BuildEnvironment.sensorGate, .physicalIPhone)
    }
}
