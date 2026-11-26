(() => {
 const map=document.getElementById('zone-map');
 if(!map)return;
 const R=6378137, rad=Math.PI/180, ns='http://www.w3.org/2000/svg';
 let form=null, drag=null;
 const preview=document.createElementNS(ns,'ellipse'), handle=document.createElementNS(ns,'circle');
 for(const [k,v] of Object.entries({fill:'#ff9800','fill-opacity':'.18',stroke:'#ff9800','stroke-width':'3'}))preview.setAttribute(k,v);
 for(const [k,v] of Object.entries({r:'8',fill:'#ff9800',stroke:'white','stroke-width':'2'}))handle.setAttribute(k,v);
 map.append(preview,handle);
 const fields=()=>['latitude','longitude','radius_metres'].map(k=>form.elements.namedItem(k));
 function render(){
  const [lat,lon,r]=fields().map(e=>Number(e.value));
  if(!Number.isFinite(lat+lon+r)||Math.abs(lat)>85||Math.abs(lon)>180||r<1||r>5000)return;
  const x=450+(R*lon*rad-Number(map.dataset.x))*1.5;
  const y=260-(R*Math.log(Math.tan(Math.PI/4+lat*rad/2))-Number(map.dataset.y))*1.5;
  const rr=r/Math.cos(lat*rad)*1.5;
  for(const [k,v] of Object.entries({cx:x,cy:y,rx:rr,ry:rr}))preview.setAttribute(k,v);
  handle.setAttribute('cx',x+rr);handle.setAttribute('cy',y);
 }
 preview.style.display=handle.style.display='none';
 document.querySelectorAll('form').forEach(f=>{
  if(!f.elements.namedItem('radius_metres'))return;
  const b=document.createElement('button');b.type='button';b.textContent='Bewerk op kaart';
  f.prepend(b);b.addEventListener('click',()=>{form=f;preview.style.display=handle.style.display='';render();map.scrollIntoView({block:'center'});});
  f.addEventListener('input',()=>{if(form===f)render();});
 });
 map.addEventListener('pointerdown',e=>{
  if(!form||![preview,handle].includes(e.target))return;
  drag=e.target===handle?'radius':'center';map.setPointerCapture(e.pointerId);e.preventDefault();
 });
 map.style.touchAction='none';
 map.addEventListener('pointermove',e=>{
  if(!drag||!form)return;
  const p=new DOMPoint(e.clientX,e.clientY).matrixTransform(map.getScreenCTM().inverse());
  const [lat,lon,r]=fields();
  if(drag==='center'){
   lon.value=((Number(map.dataset.x)+(p.x-450)/1.5)/R/rad).toFixed(7);
   lat.value=((2*Math.atan(Math.exp((Number(map.dataset.y)-(p.y-260)/1.5)/R))-Math.PI/2)/rad).toFixed(7);
  }else{
   r.value=Math.max(1,Math.min(5000,Math.hypot(p.x-Number(preview.getAttribute('cx')),p.y-Number(preview.getAttribute('cy')))/1.5*Math.cos(Number(lat.value)*rad))).toFixed(2);
  }render();
 });
 for(const event of ['pointerup','pointercancel','lostpointercapture'])map.addEventListener(event,()=>{drag=null;});
})();