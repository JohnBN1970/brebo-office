import XCTest
@testable import BreboMeasurePOC

final class ScanSampleBufferTests: XCTestCase {
    func testBufferIsBoundedAndResettable() {
        var buffer = ScanSampleBuffer(maximumSamples: 2)
        let sample = SpatialSample(worldPoint: SIMD3<Float>(1, 2, 3), confidence: 2)

        buffer.append(sample)
        buffer.append(sample)
        buffer.append(sample)
        XCTAssertEqual(buffer.samples.count, 2)

        buffer.reset()
        XCTAssertTrue(buffer.samples.isEmpty)
    }
}
