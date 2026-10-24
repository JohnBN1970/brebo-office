package nl.brebobv.onsite;
import android.content.*;
import android.database.Cursor;
import android.database.sqlite.*;
import org.json.*;
import java.time.Instant;
import java.util.*;

final class LocalStore extends SQLiteOpenHelper {
    LocalStore(Context c) { super(c,"onsite.db",null,1); }
    public void onCreate(SQLiteDatabase db) {
        db.execSQL("CREATE TABLE events(id TEXT PRIMARY KEY, payload TEXT NOT NULL, error TEXT)");
        db.execSQL("CREATE TABLE states(zone TEXT PRIMARY KEY, kind TEXT NOT NULL)");
    }
    public void onUpgrade(SQLiteDatabase db,int a,int b) { throw new IllegalStateException("Database-upgrade vereist."); }
    synchronized void enqueue(Contract.Zone z,String kind) throws Exception {
        SQLiteDatabase db=getWritableDatabase(); db.beginTransaction();
        try {
            try(Cursor c=db.rawQuery("SELECT kind FROM states WHERE zone=?",new String[]{z.key()})) {
                if(c.moveToFirst() && c.getString(0).equals(kind)) { db.setTransactionSuccessful(); return; }
            }
            String id=UUID.randomUUID().toString();
            ContentValues event=new ContentValues(); event.put("id",id); event.put("payload",Contract.event(z,kind,id,Instant.now()).toString());
            db.insertOrThrow("events",null,event);
            ContentValues state=new ContentValues(); state.put("zone",z.key()); state.put("kind",kind);
            db.insertWithOnConflict("states",null,state,SQLiteDatabase.CONFLICT_REPLACE);
            db.setTransactionSuccessful();
        } finally { db.endTransaction(); }
    }
    List<JSONObject> pending() throws Exception {
        List<JSONObject> result=new ArrayList<>();
        try(Cursor c=getReadableDatabase().rawQuery("SELECT payload FROM events WHERE error IS NULL ORDER BY rowid LIMIT 100",null)) {
            while(c.moveToNext()) result.add(new JSONObject(c.getString(0)));
        }
        return result;
    }
    void ack(String id) { getWritableDatabase().delete("events","id=?",new String[]{id}); }
    void reject(String id,int status) { ContentValues v=new ContentValues();v.put("error","Office: "+status);getWritableDatabase().update("events",v,"id=?",new String[]{id}); }
    int count() { try(Cursor c=getReadableDatabase().rawQuery("SELECT COUNT(*) FROM events",null)) { c.moveToFirst();return c.getInt(0); } }
    int rejected() { try(Cursor c=getReadableDatabase().rawQuery("SELECT COUNT(*) FROM events WHERE error IS NOT NULL",null)) { c.moveToFirst();return c.getInt(0); } }
    void clearStates() { getWritableDatabase().delete("states",null,null); }
    void clear() { getWritableDatabase().delete("events",null,null);getWritableDatabase().delete("states",null,null); }
}
