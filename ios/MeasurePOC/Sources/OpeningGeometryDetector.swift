import Foundation
import simd

struct OpeningGeometryDetector {
    enum DetectionError: Error {
        case insufficientGeometry
        case noOpeningCandidate
    }

    /// First deterministic geometry boundary for the scanner.
    ///
    /// The next implementation step feeds stable planes extracted from the
    /// ARKit mesh/depth capture into this detector. No production dimensions
    /// may be emitted until four opening boundaries are supported by enough
    /// samples and the residual/uncertainty gate passes.
    func detect(from planes: [DetectedPlane]) throws -> OpeningGeometry {
        guard planes.count >= 4 else {
            throw DetectionError.insufficientGeometry
        }

        // Deliberately fail closed until plane classification/intersection is
        // implemented and empirically validated on a real opening.
        throw DetectionError.noOpeningCandidate
    }
}
