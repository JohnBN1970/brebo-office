const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(__dirname + '/../public/office-onsite-project-map.php', 'utf8');
assert(source.includes('min="0.01" max="5000" step="0.01"'), 'range must preserve stored centimetre precision');
let script = source.slice(source.lastIndexOf('<script>') + 8, source.lastIndexOf('</script>'));
script = script.replace(/const initial=.*?;/, 'const initial={lat:51.5,lng:4.3};')
  .replace(/const editableZones=.*?;/, 'const editableZones=[{id:"1",revision:"7",name:"Existing",lat:51.51,lng:4.31,radius:155.25}];')
  .replace(/building_id:.*?,zone_name:name/, 'building_id:"1",zone_name:name');
const nodes = {};
function node(id) {
  return nodes[id] ||= {id,value:'',disabled:true,hidden:true,events:{},dataset:{zoneId:'1'},
    addEventListener(type,fn) { this.events[type]=fn; }};
}
node('radius').value='150'; node('zone-name').value='Werkgebied';
const appended=[];
const document={getElementById:node,querySelectorAll:()=>[node('edit-button')],createElement:()=>({}),head:{appendChild:item=>appended.push(item)}};
let drawnRadius;
const layer={addTo(){return this;},setLatLng(){},setRadius(value){drawnRadius=value;},on(){}};
const L={map:()=>({setView(){return this;},on(){}}),tileLayer:()=>layer,circle:(where,options)=>{drawnRadius=options.radius;return layer;},marker:()=>layer};
vm.runInNewContext(script,{document,L,TextEncoder});
assert.equal(appended.length,0); console.log('PASS: map assets wait for consent');
node('edit-button').events.click();
assert.equal(node('radius').value,'155.25'); assert.equal(node('edit-submit').disabled,true);
console.log('PASS: edit before map activation preserves radius and disables submit');
node('enable').events.click(); appended.find(x=>x.src).onload();
assert.equal(drawnRadius,155.25); assert.equal(node('edit-submit').disabled,false); assert.equal(node('save-zone').disabled,true);
console.log('PASS: activation draws precise existing circle and prevents duplicate create');
node('zone-edit').events.submit({preventDefault(){throw Error('unexpected rejection');}});
assert.equal(node('edit-radius').value,'155.25'); assert.equal(node('edit-revision').value,'7');
console.log('PASS: unchanged edit submits exact radius and original revision');
node('radius').value='250.75'; node('radius').events.input();
assert.equal(drawnRadius,250.75);
node('zone-edit').events.submit({preventDefault(){throw Error('unexpected rejection');}});
assert.equal(node('edit-radius').value,'250.75');
console.log('PASS: radius adjustment updates circle and submitted value');
node('edit-cancel').events.click();
assert.equal(node('zone-edit').hidden,true); assert.equal(node('save-zone').disabled,false);
console.log('PASS: cancel leaves edit mode without a request');
