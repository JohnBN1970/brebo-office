import Foundation

@MainActor
final class OnSitePresenceUploader: ObservableObject {
    @Published private(set) var lastError: String?
    @Published private(set) var lastUploadedEventId: UUID?

    private let apiClient = OnSiteAPIClient()
    private let queueKey = "brebo.onsite.pendingPresenceEvents.v1"
    private var pending: [CrewPresenceEvent] = []
    private var uploading = false

    init() {
        if let data = UserDefaults.standard.data(forKey: queueKey),
           let decoded = try? JSONDecoder().decode([CrewPresenceEvent].self, from: data) {
            pending = decoded
        }
    }

    func uploadNewEvents(_ events: [CrewPresenceEvent]) async {
        let existing = Set(pending.map(\.id))
        pending.append(contentsOf: events.filter { !existing.contains($0.id) })
        save()
        await flush()
    }

    func flush() async {
        guard !uploading, let token = OnSiteSecureStore.deviceToken() else { return }
        uploading = true
        defer { uploading = false }

        while let event = pending.first {
            do {
                try await apiClient.recordPresence(event: event, deviceToken: token)
                pending.removeFirst()
                save()
                lastUploadedEventId = event.id
                lastError = nil
            } catch {
                lastError = error.localizedDescription
                break
            }
        }
    }

    private func save() {
        guard let data = try? JSONEncoder().encode(pending) else { return }
        UserDefaults.standard.set(data, forKey: queueKey)
    }
}
