import SwiftUI

struct ScanContentView: View {
    @State private var scanning = false
    @State private var depthFrames = 0
    @State private var meshAnchors = 0
    @State private var samples = ScanSampleBuffer()
    @State private var detectedPlanes = 0
    @State private var openingCandidate: OpeningCandidate?
    private let qualityGate = ScanQualityGate()

    var body: some View {
        ZStack(alignment: .bottom) {
            LidarScanView(
                isScanning: scanning,
                onProgress: { frames, anchors in
                    depthFrames = frames
                    meshAnchors = anchors
                },
                onMeshPoints: { points in
                    guard scanning else { return }
                    for point in points {
                        samples.append(SpatialSample(worldPoint: point, confidence: nil))
                    }
                }
            )
            .ignoresSafeArea()

            VStack(spacing: 10) {
                Text(ResearchBanner.text)
                    .font(.caption.bold())
                Text(scanning ? "Sparing scannen" : "BREBO Measure")
                    .font(.headline)
                Text(ScanBuildNotes.display)
                    .font(.caption2)
                if scanning {
                    ProgressView(value: ScanCoverage(depthFrames: depthFrames, meshAnchors: meshAnchors).progress(using: qualityGate))
                }
                Text("Depth: \(depthFrames)  •  Mesh: \(meshAnchors)  •  Punten: \(samples.samples.count)")
                    .font(.caption)
                    .monospacedDigit()
                if detectedPlanes > 0 {
                    Text("Kandidaatvlakken: \(detectedPlanes)")
                        .font(.caption.bold())
                }
                if let candidate = openingCandidate {
                    VStack(spacing: 4) {
                        Text(String(format: "Sparing kandidaat %.0f × %.0f mm", candidate.widthMm, candidate.heightMm))
                            .font(.headline)
                        Text(String(format: "Betrouwbaarheid %.0f%%  •  onzekerheid %.1f mm", candidate.confidence * 100, candidate.uncertaintyMm))
                            .font(.caption)
                    }
                }
                Text(instruction)
                    .font(.subheadline)

                Button(scanning ? "Scan stoppen en analyseren" : "Start scan") {
                    if scanning {
                        analyse()
                        scanning = false
                    } else {
                        depthFrames = 0
                        meshAnchors = 0
                        detectedPlanes = 0
                        openingCandidate = nil
                        samples.reset()
                        scanning = true
                    }
                }
                .buttonStyle(.borderedProminent)
            }
            .padding()
            .frame(maxWidth: .infinity)
            .background(.ultraThinMaterial)
        }
    }

    private var instruction: String {
        guard scanning else {
            if openingCandidate != nil {
                return "Automatische sparingskandidaat gevonden - proefmeting controleren"
            }
            return detectedPlanes > 0
                ? "Vlakken gevonden, maar nog geen betrouwbare rechthoekige sparing"
                : "Geen meetpunten aantikken"
        }
        let verdict = qualityGate.evaluate(depthFrames: depthFrames, meshAnchors: meshAnchors)
        return ScanStatus(depthFrames: depthFrames, meshAnchors: meshAnchors, verdict: verdict).message
    }

    private func analyse() {
        let points = samples.samples.map(\.worldPoint)
        let planes = AxisPlaneDetector().detect(from: points)
        detectedPlanes = planes.count
        openingCandidate = RectangularOpeningSelector().select(from: planes)?.candidate
    }
}
