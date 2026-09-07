import SwiftUI

struct ContentView: View {
    @State private var distanceMillimetres: Double?
    @State private var pointCount = 0
    @State private var resetToken = UUID()

    var body: some View {
        ZStack(alignment: .bottom) {
            LidarMeasureView(
                resetToken: resetToken,
                onMeasurementChanged: { count, distance in
                    pointCount = count
                    distanceMillimetres = distance
                }
            )
            .ignoresSafeArea()

            VStack(spacing: 12) {
                Text(statusText)
                    .font(.headline)

                if let distanceMillimetres {
                    Text(String(format: "%.1f mm", distanceMillimetres))
                        .font(.system(size: 42, weight: .bold, design: .rounded))
                        .monospacedDigit()
                }

                Button("Nieuwe meting") {
                    resetToken = UUID()
                    pointCount = 0
                    distanceMillimetres = nil
                }
                .buttonStyle(.borderedProminent)
            }
            .padding()
            .frame(maxWidth: .infinity)
            .background(.ultraThinMaterial)
        }
    }

    private var statusText: String {
        switch pointCount {
        case 0:
            return "Tik het eerste meetpunt aan"
        case 1:
            return "Tik het tweede meetpunt aan"
        default:
            return "Proefmeting gereed"
        }
    }
}
