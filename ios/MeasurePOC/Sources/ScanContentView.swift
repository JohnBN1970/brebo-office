import SwiftUI

struct ScanContentView: View {
    @State private var scanning = false
    @State private var depthFrames = 0
    @State private var meshAnchors = 0
    private let qualityGate = ScanQualityGate()

    var body: some View {
        ZStack(alignment: .bottom) {
            LidarScanView(isScanning: scanning) { frames, anchors in
                depthFrames = frames
                meshAnchors = anchors
            }
            .ignoresSafeArea()

            VStack(spacing: 10) {
                Text(scanning ? "Sparing scannen" : "BREBO Measure")
                    .font(.headline)
                Text("Depth frames: \(depthFrames)  •  Mesh: \(meshAnchors)")
                    .font(.caption)
                    .monospacedDigit()
                Text(instruction)
                    .font(.subheadline)

                Button(scanning ? "Scan stoppen" : "Start scan") {
                    scanning.toggle()
                    if scanning {
                        depthFrames = 0
                        meshAnchors = 0
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
        guard scanning else { return "Geen meetpunten aantikken" }
        let verdict = qualityGate.evaluate(depthFrames: depthFrames, meshAnchors: meshAnchors)
        return ScanStatus(depthFrames: depthFrames, meshAnchors: meshAnchors, verdict: verdict).message
    }
}
