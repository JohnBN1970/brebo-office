import CryptoKit
import Foundation

/// Persist evidence per device credential. Never replay one worker's queue as another.
final class OnSitePresenceQueue {
    private let defaults: UserDefaults
    private let key: String
    private(set) var events: [CrewPresenceEvent]

    init(deviceToken: String, defaults: UserDefaults = .standard) {
        self.defaults = defaults
        key = Self.storageKey(deviceToken)
        events = defaults.data(forKey: key)
            .flatMap { try? JSONDecoder().decode([CrewPresenceEvent].self, from: $0) } ?? []
    }

    func enqueue(_ incoming: [CrewPresenceEvent]) {
        var ids = Set(events.map(\.id))
        events.append(contentsOf: incoming.filter { ids.insert($0.id).inserted })
        persist()
    }

    func acknowledge(_ id: UUID) {
        events.removeAll { $0.id == id }
        persist()
    }

    static func clear(deviceToken: String, defaults: UserDefaults = .standard) {
        defaults.removeObject(forKey: storageKey(deviceToken))
    }

    private static func storageKey(_ token: String) -> String {
        let digest = SHA256.hash(data: Data(token.utf8)).map { String(format: "%02x", $0) }.joined()
        return "onsite.pending-presence-events." + digest
    }

    private func persist() {
        guard let data = try? JSONEncoder().encode(events) else { return }
        defaults.set(data, forKey: key)
    }
}
