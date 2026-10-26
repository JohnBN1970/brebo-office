import BackgroundTasks
import Combine
import Network
import UIKit

/// Process-owned services: location launches must work before a SwiftUI scene exists.
@MainActor
final class OnSiteRuntime {
    static let shared = OnSiteRuntime()
    static let refreshIdentifier = "nl.brebo.onsite.refresh"

    let identity = OnSiteIdentityStore()
    let location = CrewLocationMonitor()
    let uploader = OnSitePresenceUploader()
    private let network = NWPathMonitor()
    private var subscriptions = Set<AnyCancellable>()
    private var refreshInProgress = false
    private var lastRefreshAttempt: Date?
    private var started = false
    private var refreshTimer: Timer?

    private init() {
        location.onEvent = { [weak self] event in
            // Persist during the delegate callback, independently of view rendering.
            self?.uploader.enqueue(event)
        }
        location.onAuthorizationChanged = { [weak self] in
            guard let self else { return }
            self.syncProjectZones(self.identity.assignedProjects)
            self.wake()
        }
        location.onWake = { [weak self] in self?.wake() }
        identity.$officeApprovals.sink { [weak self] approvals in
            guard let self else { return }
            // A reset or account switch must never retain another employee's approvals.
            self.uploader.applyOfficeApprovals(approvals ?? [:])
        }.store(in: &subscriptions)
        identity.$assignedProjects.sink { [weak self] projects in
            self?.syncProjectZones(projects)
        }.store(in: &subscriptions)
        // Cached assignments are available immediately, even without a network.
        syncProjectZones(identity.assignedProjects)
    }

    func start() {
        guard !started else { return }
        started = true
        network.pathUpdateHandler = { [weak self] path in
            guard path.status == .satisfied else { return }
            Task { @MainActor in self?.wake() }
        }
        network.start(queue: DispatchQueue(label: "nl.brebo.onsite.network"))
        refreshTimer = Timer.scheduledTimer(withTimeInterval: 300, repeats: true) { [weak self] _ in
            Task { @MainActor in self?.wake(forceRefresh: true) }
        }
        scheduleRefresh()
        wake(forceRefresh: true)
    }

    func wake(forceRefresh: Bool = false) {
        // Bound native event uploads/bootstrap to iOS-granted execution time.
        let lease = OnSiteBackgroundLease()
        let work = Task { [weak self] in
            defer { lease.end() }
            guard let self else { return }
            await self.uploader.retryPending()
            await self.refresh(force: forceRefresh)
            await self.uploader.retryPending()
        }
        lease.onExpiration = { work.cancel() }
    }

    func refresh(force: Bool) async {
        guard !refreshInProgress else { return }
        if !force, let lastRefreshAttempt, Date().timeIntervalSince(lastRefreshAttempt) < 300 { return }
        refreshInProgress = true
        lastRefreshAttempt = Date()
        defer { refreshInProgress = false }
        identity.restoreCachedIdentity()
        await identity.refreshBootstrap()
        syncProjectZones(identity.assignedProjects)
    }

    private func syncProjectZones(_ projects: [OnSiteBootstrapProject]) {
        let zones = projects.flatMap { project in
            project.zones.map { zone in
                CrewProjectZone(id: zone.id, projectId: project.id, projectName: project.name,
                                latitude: zone.latitude, longitude: zone.longitude,
                                radiusMetres: zone.radiusMetres)
            }
        }
        location.monitor(zones)
    }

    func scheduleRefresh() {
        guard OnSiteSecureStore.deviceToken() != nil else { return }
        let request = BGAppRefreshTaskRequest(identifier: Self.refreshIdentifier)
        request.earliestBeginDate = Date(timeIntervalSinceNow: 15 * 60)
        // iOS chooses when to run this; region callbacks remain the primary trigger.
        try? BGTaskScheduler.shared.submit(request)
    }

    func handleRefresh(_ task: BGAppRefreshTask) {
        scheduleRefresh()
        let work = Task {
            await refresh(force: true)
            await uploader.retryPending()
            task.setTaskCompleted(success: !Task.isCancelled && uploader.pendingCount == 0)
        }
        task.expirationHandler = { work.cancel() }
    }
}

@MainActor
private final class OnSiteBackgroundLease {
    private var identifier: UIBackgroundTaskIdentifier = .invalid
    var onExpiration: (() -> Void)?

    init() {
        identifier = UIApplication.shared.beginBackgroundTask(withName: "OnSite synchronisatie") { [weak self] in
            Task { @MainActor in
                self?.onExpiration?()
                self?.end()
            }
        }
    }

    func end() {
        guard identifier != .invalid else { return }
        UIApplication.shared.endBackgroundTask(identifier)
        identifier = .invalid
    }
}

@MainActor
final class OnSiteAppDelegate: NSObject, UIApplicationDelegate {
    func application(_ application: UIApplication,
                     didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]? = nil) -> Bool {
        // Eagerly build the location delegate for .location background relaunches.
        let runtime = OnSiteRuntime.shared
        BGTaskScheduler.shared.register(forTaskWithIdentifier: OnSiteRuntime.refreshIdentifier,
                                        using: .main) { task in
            guard let refreshTask = task as? BGAppRefreshTask else { return }
            Task { @MainActor in runtime.handleRefresh(refreshTask) }
        }
        runtime.start()
        return true
    }

    func applicationDidBecomeActive(_ application: UIApplication) {
        OnSiteRuntime.shared.wake(forceRefresh: true)
    }

    func applicationDidEnterBackground(_ application: UIApplication) {
        OnSiteRuntime.shared.scheduleRefresh()
        OnSiteRuntime.shared.wake()
    }
}
