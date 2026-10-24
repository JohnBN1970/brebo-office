package nl.brebobv.onsite;
import android.Manifest;
import android.app.PendingIntent;
import android.content.*;
import android.content.pm.PackageManager;
import android.os.Build;
import com.google.android.gms.location.*;
import com.google.android.gms.tasks.Tasks;
import org.json.JSONObject;
import java.util.*;
import java.util.concurrent.TimeUnit;

final class Zones {
    static final long TTL=24*60*60*1000L;
    static android.content.SharedPreferences prefs(Context c) { return c.getSharedPreferences("onsite",0); }
    static boolean locationEnabled(Context c) {
        android.location.LocationManager m=(android.location.LocationManager)c.getSystemService(Context.LOCATION_SERVICE);
        return m!=null && (Build.VERSION.SDK_INT>=28?m.isLocationEnabled():m.isProviderEnabled(android.location.LocationManager.GPS_PROVIDER)||m.isProviderEnabled(android.location.LocationManager.NETWORK_PROVIDER));
    }
    static boolean allowed(Context c) {
        return locationEnabled(c) && prefs(c).getBoolean("consent",false) &&
            c.checkSelfPermission(Manifest.permission.ACCESS_FINE_LOCATION)==PackageManager.PERMISSION_GRANTED &&
            (Build.VERSION.SDK_INT<29 || c.checkSelfPermission(Manifest.permission.ACCESS_BACKGROUND_LOCATION)==PackageManager.PERMISSION_GRANTED);
    }
    static PendingIntent intent(Context c) {
        return PendingIntent.getBroadcast(c,17,new Intent(c,ZoneReceiver.class).setAction("nl.brebobv.onsite.ZONE"),
            PendingIntent.FLAG_UPDATE_CURRENT | PendingIntent.FLAG_MUTABLE);
    }
    static void stop(Context c) {
        LocationServices.getGeofencingClient(c).removeGeofences(intent(c));
        prefs(c).edit().putBoolean("registered",false).apply();
        try(LocalStore store=new LocalStore(c)){store.clearStates();}
    }
    static void register(Context c,JSONObject bootstrap) throws Exception {
        List<Contract.Zone> zones=Contract.zones(bootstrap);
        if (!allowed(c)) { stop(c); return; }
        String snapshot=bootstrap.getJSONArray("projects").toString();
        if(prefs(c).getBoolean("registered",false) && snapshot.equals(prefs(c).getString("registered_snapshot","")) &&
            System.currentTimeMillis()-prefs(c).getLong("registered_at",0)<TTL/2) return;
        GeofencingClient client=LocationServices.getGeofencingClient(c);
        Tasks.await(client.removeGeofences(intent(c)),30,TimeUnit.SECONDS);
        prefs(c).edit().putBoolean("registered",false).commit();
        List<Geofence> fences=new ArrayList<>();
        for(Contract.Zone z:zones) fences.add(new Geofence.Builder().setRequestId(z.key())
            .setCircularRegion(z.latitude(),z.longitude(),z.radius()).setExpirationDuration(TTL)
            .setTransitionTypes(Geofence.GEOFENCE_TRANSITION_ENTER|Geofence.GEOFENCE_TRANSITION_EXIT)
            .setNotificationResponsiveness(60000).build());
        // Initial location is not an arrival event. Only observed transitions create evidence.
        if(!fences.isEmpty()) Tasks.await(client.addGeofences(new GeofencingRequest.Builder().setInitialTrigger(0).addGeofences(fences).build(),intent(c)),30,TimeUnit.SECONDS);
        if(!allowed(c)) { stop(c); return; }
        prefs(c).edit().putBoolean("registered",!fences.isEmpty()).putLong("registered_at",System.currentTimeMillis()).putString("registered_snapshot",snapshot).commit();
    }
}

