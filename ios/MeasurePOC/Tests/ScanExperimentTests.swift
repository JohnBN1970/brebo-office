import XCTest
@testable import BreboMeasurePOC

final class ScanExperimentTests: XCTestCase {
    func testDeviationIsMeasuredAgainstIndependentReference() {
        let diagnostics = ScanDiagnostics.make(depthFrames: 100, meshAnchors: 5, durationSeconds: 10)
        let experiment = ScanExperiment(
            openingReference: "test-opening",
            referenceWidthMm: 1200,
            referenceHeightMm: 2100,
            diagnostics: diagnostics,
            detectedWidthMm: 1203,
            detectedHeightMm: 2098,
            uncertaintyMm: 2
        )
        XCTAssertEqual(experiment.widthDeviationMm, 3)
        XCTAssertEqual(experiment.heightDeviationMm, -2)
    }
}
