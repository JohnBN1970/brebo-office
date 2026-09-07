import XCTest
@testable import BreboMeasurePOC

final class ResearchBannerTests: XCTestCase {
    func testBannerCannotBeMistakenForProductionMeasurement() {
        XCTAssertTrue(ResearchBanner.text.contains("NIET VOOR PRODUCTIE"))
    }
}
