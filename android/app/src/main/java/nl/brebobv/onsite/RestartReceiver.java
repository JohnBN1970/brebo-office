package nl.brebobv.onsite;
import android.content.*;
public final class RestartReceiver extends BroadcastReceiver {
    public void onReceive(Context c,Intent i) {
        Zones.prefs(c).edit().putBoolean("registered",false).apply();
        SyncWorker.schedule(c);
    }
}
