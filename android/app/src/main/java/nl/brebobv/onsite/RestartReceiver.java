package nl.brebobv.onsite;
import android.content.*;
public final class RestartReceiver extends BroadcastReceiver {
    public void onReceive(Context c,Intent i) {
        if(!Intent.ACTION_BOOT_COMPLETED.equals(i.getAction()) && !Intent.ACTION_MY_PACKAGE_REPLACED.equals(i.getAction()))return;
        Zones.prefs(c).edit().putBoolean("registered",false).apply();
        SyncWorker.schedule(c);
    }
}
