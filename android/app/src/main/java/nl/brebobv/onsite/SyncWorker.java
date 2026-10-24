package nl.brebobv.onsite;
import android.content.Context;
import androidx.work.*;
import org.json.*;
import java.util.concurrent.TimeUnit;

public final class SyncWorker extends Worker {
    public SyncWorker(Context c,WorkerParameters p) { super(c,p); }
    static final Object LOCK=new Object();
    static void schedule(Context c) {
        Constraints constraints=new Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build();
        WorkManager w=WorkManager.getInstance(c);
        w.enqueueUniqueWork("onsite-sync",ExistingWorkPolicy.APPEND_OR_REPLACE,
            new OneTimeWorkRequest.Builder(SyncWorker.class).setConstraints(constraints).setBackoffCriteria(BackoffPolicy.EXPONENTIAL,30,TimeUnit.SECONDS).build());
        w.enqueueUniquePeriodicWork("onsite-refresh",ExistingPeriodicWorkPolicy.KEEP,
            new PeriodicWorkRequest.Builder(SyncWorker.class,6,TimeUnit.HOURS).setConstraints(constraints).build());
    }
    public Result doWork() {
        synchronized(LOCK) {
            Context c=getApplicationContext();
            try(LocalStore store=new LocalStore(c)) {
                String token=new Vault(c).token();
                if(token==null)return Result.success();
                Api api=new Api();
                JSONObject bootstrap=api.call("bootstrap",token,null);
                Contract.zones(bootstrap);
                Zones.prefs(c).edit().putString("bootstrap",bootstrap.toString()).putLong("fetched_at",System.currentTimeMillis()).putString("error","").commit();
                // Upload previously observed transitions even when the user subsequently pauses monitoring.
                for(JSONObject event:store.pending()) {
                    try {
                        JSONObject response=api.call("presence",token,event);
                        if(!response.optBoolean("ok",false))return Result.retry();
                        store.ack(event.getString("event_id"));
                    } catch(Api.Failure failure) {
                        if(failure.status==401)throw failure;
                        if(failure.status==400 || failure.status==403 || failure.status==404 || failure.status==409 || failure.status==422) {
                            store.reject(event.getString("event_id"),failure.status);
                        } else throw failure;
                    }
                }
                Zones.register(c,bootstrap);
                return store.pending().isEmpty()?Result.success():Result.retry();
            } catch(Api.Failure error) {
                if(error.status==401) {
                    Zones.stop(c); Zones.prefs(c).edit().putBoolean("consent",false).remove("bootstrap").putString("error","Toestelkoppeling verlopen of ingetrokken. Activeer opnieuw.").commit();
                    new Vault(c).clear();
                    return Result.failure();
                }
                return failed(c);
            } catch(Exception error) { return failed(c); }
        }
    }
    private Result failed(Context c) {
        Zones.prefs(c).edit().putString("error","Synchronisatie niet gelukt. Meldingen blijven op dit toestel bewaard.").commit();
        return Result.retry();
    }
}

