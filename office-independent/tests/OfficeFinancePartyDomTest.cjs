'use strict';
const assert=require('node:assert/strict');const fs=require('node:fs');
const {JSDOM}=require(process.env.SALES_JSDOM_MODULE||'jsdom');
const dom=new JSDOM(fs.readFileSync(process.argv[2],'utf8'),{runScripts:'dangerously',url:'https://brebobv.nl/office-test/finance/verkoop?new=1'});
const d=dom.window.document,project=d.querySelector('select[name="office_project_id"]'),debtor=d.querySelector('[name="relation_id"]'),contact=d.querySelector('[name="contact_id"]'),ref=d.querySelector('[name="reference"]');
ref.value='Client purchase order';project.value='10';project.dispatchEvent(new dom.window.Event('change',{bubbles:true}));
assert.equal(debtor.value,'4');assert.equal(contact.value,'8');assert.equal(ref.value,'Client purchase order');assert.match(d.querySelector('#sales-party-preview').textContent,/invoice@example.com/);assert.match(d.querySelector('#sales-party-preview').textContent,/Street 1/);
project.value='';project.dispatchEvent(new dom.window.Event('change',{bubbles:true}));assert.equal(debtor.value,'');assert.equal(contact.value,'0');assert.equal(ref.value,'Client purchase order');
console.log('OFFICE_FINANCE_PARTY_DOM_OK');
