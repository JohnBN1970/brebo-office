package nl.brebobv.onsite;
import android.content.*;
import com.google.android.gms.location.*;
import org.json.JSONObject;
import java.util.concurrent.Executors;

public final class ZoneReceiver extends BroadcastReceiver {
    private static final java.util.concurrent.ExecutorService IO=Executors.newSingleThreadExecutor();
    public void onReceive(Context c,Intent intent) {
        PendingResult pending=goAsync();
        IO.execute(()-> {
            try {
                synchronized(SyncWorker.LOCK) {
                    if(!Zones.allowed(c) || new Vault(c).token()==null)return;
                    if(System.currentTimeMillis()-Zones.prefs(c).getLong("fetched_at",0)>Zones.TTL) {
                        Zones.stop(c); SyncWorker.schedule(c); return;
                    }
                    GeofencingEvent event=GeofencingEvent.fromIntent(intent);
                    if(event==null)return;
                    if(event.hasError()) {
                        Zones.prefs(c).edit().putBoolean("registered",false).putString("error","Locatiebewaking onderbroken. Open OnSite om opnieuw te verbinden.").commit();
                        SyncWorker.schedule(c);return;
                    }
                    int transition=event.getGeofenceTransition();
                    if(transition!=Geofence.GEOFENCE_TRANSITION_ENTER && transition!=Geofence.GEOFENCE_TRANSITION_EXIT)return;
                    java.util.List<Geofence> triggered=event.getTriggeringGeofences();
                    if(triggered==null)return;
                    JSONObject bootstrap=new JSONObject(Zones.prefs(c).getString("bootstrap","{}"));
                    try(LocalStore store=new LocalStore(c)) {
                        for(Contract.Zone zone:Contract.zones(bootstrap))
                            for(Geofence fence:triggered)
                                if(zone.key().equals(fence.getRequestId()))store.enqueue(zone,transition==Geofence.GEOFENCE_TRANSITION_ENTER?"in":"out");
                    }
                    SyncWorker.schedule(c);
                }
            } catch(Exception e) {
                Zones.prefs(c).edit().putString("error","Aanwezigheidsmelding kon niet worden verwerkt. Open OnSite.").commit();
            } finally { pending.finish(); }
        });
    }
}

