import Foundation

struct OpeningSizeSanity {
    static func isPlausible(widthMm: Double, heightMm: Double) -> Bool {
        (200...5000).contains(widthMm) && (200...5000).contains(heightMm)
    }
}
