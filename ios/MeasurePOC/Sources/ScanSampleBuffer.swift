import Foundation
import simd

struct SpatialSample {
    let worldPoint: SIMD3<Float>
    let confidence: UInt8?
}

struct ScanSampleBuffer {
    private(set) var samples: [SpatialSample] = []
    let maximumSamples: Int

    init(maximumSamples: Int = 50_000) {
        self.maximumSamples = maximumSamples
    }

    mutating func append(_ sample: SpatialSample) {
        guard samples.count < maximumSamples else { return }
        samples.append(sample)
    }

    mutating func reset() {
        samples.removeAll(keepingCapacity: true)
    }
}
