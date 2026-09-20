import Foundation

@MainActor
final class OnSitePresenceUploader: ObservableObject {
    @Published private(set) var lastError: String?
    @Published private(set) var lastUploadedEventId: UUID?

    private let apiClient = OnSiteAPIClient()
    private var uploaded: Set<UUID> = []

    func uploadNewEvents(_ events: [CrewPresenceEvent]) async {
        guard let token = OnSiteSecureStore.deviceToken() else { return }

        for event in events where !uploaded.contains(event.id) {
            do {
                try await apiClient.recordPresence(event: event, deviceToken: token)
                uploaded.insert(event.id)
                lastUploadedEventId = event.id
                lastError = nil
            } catch {
                lastError = error.localizedDescription
                break
            }
        }
    }
}
