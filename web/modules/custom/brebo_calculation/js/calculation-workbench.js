(function (Drupal, once, drupalSettings) {
  'use strict';

  Drupal.behaviors.breboCalculationWorkbench = {
    attach(context) {
      once('brebo-calc-workbench', '.brebo-calc-workbench__grid--editable', context).forEach((grid) => {
        const form = grid.closest('form');
        if (!form) return;
        const saveButton = form.querySelector('[data-drupal-selector="edit-workbench-actions-save"]');
        if (!saveButton || saveButton.disabled) return;

        let timer = null, dirty = false, saving = false;
        const settings = drupalSettings.breboCalculation?.commercial || {};
        const money = (value) => new Intl.NumberFormat('nl-NL', {style:'currency',currency:'EUR',minimumFractionDigits:2,maximumFractionDigits:2}).format(Number.isFinite(value) ? value : 0);
        const percent = (value) => `${(Number.isFinite(value) ? value : 0).toLocaleString('nl-NL',{minimumFractionDigits:1,maximumFractionDigits:2})}%`;
        const num = (input) => { if (!input) return 0; const value = parseFloat(String(input.value).replace(',', '.')); return Number.isFinite(value) ? value : 0; };
        const pct = (name) => Number(settings[name] || 0) / 100;
        const structureRows = Array.from(grid.querySelectorAll('tr.brebo-calc-workbench__structure'));

        const commercialFor = (direct, values = settings) => {
          if (values.method === 'single_margin') {
            const margin = direct * (Number(values.singleMarginPct || 0) / 100);
            return {generalCost:0,risk:0,margin,salesPrice:direct + margin};
          }
          const generalCost = direct * (Number(values.generalCostPct || 0) / 100);
          const risk = (direct + generalCost) * (Number(values.riskPct || 0) / 100);
          const margin = (direct + generalCost + risk) * (Number(values.profitPct || 0) / 100);
          return {generalCost,risk,margin,salesPrice:direct + generalCost + risk + margin};
        };
        const commercial = (direct) => commercialFor(direct, settings);

        const lineTotal = (row) => {
          const quantity = num(row.querySelector('input[name$="[quantity]"]'));
          const components = {labour:quantity*num(row.querySelector('input[name$="[labour_unit_cost]"]')),material:quantity*num(row.querySelector('input[name$="[material_unit_cost]"]')),equipment:quantity*num(row.querySelector('input[name$="[equipment_unit_cost]"]')),subcontracting:quantity*num(row.querySelector('input[name$="[subcontracting_unit_cost]"]')),other:quantity*num(row.querySelector('input[name$="[other_unit_cost]"]'))};
          const total = Object.values(components).reduce((sum,value)=>sum+value,0);
          const unitTotal = quantity > 0 ? total / quantity : 0;
          const type = row.querySelector('select[name$="[rule_type]"]')?.value || 'normal';
          return {unitTotal,total,components,included:type!=='option'&&type!=='note',type,commercial:commercial(total)};
        };
        const childRows = (structureRow) => {
          const rows=[]; const isMainGroup=structureRow.classList.contains('type-main_group'); let current=structureRow.nextElementSibling;
          while(current){if(current.classList.contains('brebo-calc-workbench__structure')){if(isMainGroup&&current.classList.contains('depth-0'))break;if(!isMainGroup)break;}rows.push(current);current=current.nextElementSibling;} return rows;
        };
        const setText=(root,selector,value)=>{const el=root.querySelector(selector);if(el)el.textContent=money(value);};

        const scenarioRoot = form.querySelector('.brebo-calc-scenarios');
        const scenarioDefaults = {
          basis: {factor:1},
          scherp: {factor:.75},
          doel: {factor:1.25},
        };
        const scenarioValues = (name) => {
          const factor = scenarioDefaults[name]?.factor ?? 1;
          const card = scenarioRoot?.querySelector(`[data-scenario="${name}"]`);
          const value = (key, base) => num(card?.querySelector(`[data-scenario-input="${key}"]`)) || base;
          if (settings.method === 'single_margin') return {method:'single_margin',singleMarginPct:value('singleMarginPct',Number(settings.singleMarginPct||0)*factor)};
          return {method:'tail_costs',generalCostPct:value('generalCostPct',Number(settings.generalCostPct||0)),riskPct:value('riskPct',Number(settings.riskPct||0)*factor),profitPct:value('profitPct',Number(settings.profitPct||0)*factor)};
        };
        const buildScenarios = () => {
          if (!scenarioRoot) return;
          scenarioRoot.querySelectorAll('.brebo-calc-scenario').forEach((card) => {
            const name=card.dataset.scenario; const factor=scenarioDefaults[name]?.factor??1; const inputs=card.querySelector('.brebo-calc-scenario__inputs'); if(!inputs||inputs.children.length) return;
            const fields=settings.method==='single_margin'?[['singleMarginPct','Marge %',Number(settings.singleMarginPct||0)*factor]]:[['generalCostPct','AK %',Number(settings.generalCostPct||0)],['riskPct','Risico %',Number(settings.riskPct||0)*factor],['profitPct','Winst %',Number(settings.profitPct||0)*factor]];
            fields.forEach(([key,label,value])=>{const wrap=document.createElement('label');wrap.textContent=label;const input=document.createElement('input');input.type='number';input.step='0.1';input.min='0';input.value=Number(value).toFixed(2);input.dataset.scenarioInput=key;wrap.appendChild(input);inputs.appendChild(wrap);});
          });
          scenarioRoot.addEventListener('input', refreshClientTotals);
        };
        const refreshScenarios = (direct) => {
          if (!scenarioRoot) return;
          ['basis','scherp','doel'].forEach((name)=>{const card=scenarioRoot.querySelector(`[data-scenario="${name}"]`);if(!card)return;const result=commercialFor(direct,scenarioValues(name));const adjustment=Number(settings.commercialAdjustment||0);const sales=result.salesPrice+adjustment;const gross=sales-direct;const grossPct=sales>0?(gross/sales)*100:0;setText(card,'[data-scenario-output="sales"]',sales);setText(card,'[data-scenario-output="gross"]',gross);const pctEl=card.querySelector('[data-scenario-output="gross-pct"]');if(pctEl)pctEl.textContent=percent(grossPct);});
        };

        const refreshClientTotals=()=>{
          const grand={direct:0,labour:0,material:0,equipment:0,subcontracting:0,other:0};
          grid.querySelectorAll('tr.brebo-calc-workbench__line').forEach((row)=>{const calc=lineTotal(row);const derived=row.querySelectorAll('.brebo-calc-derived');if(derived[0])derived[0].textContent=money(calc.unitTotal);if(derived[1])derived[1].textContent=money(calc.total);setText(row,'[data-commercial-kind="general-cost"]',calc.commercial.generalCost);setText(row,'[data-commercial-kind="risk"]',calc.commercial.risk);setText(row,'[data-commercial-kind="margin"]',calc.commercial.margin);setText(row,'[data-commercial-kind="sales-price"]',calc.commercial.salesPrice);row.className=row.className.replace(/\brule-[^\s]+/g,'').trim()+` rule-${calc.type}`;if(calc.included){grand.direct+=calc.total;Object.keys(calc.components).forEach((key)=>{grand[key]+=calc.components[key];});}});
          structureRows.forEach((row)=>{const subtotal={direct:0,labour:0,material:0,equipment:0,subcontracting:0,other:0};childRows(row).forEach((child)=>{if(!child.classList.contains('brebo-calc-workbench__line'))return;const calc=lineTotal(child);if(!calc.included)return;subtotal.direct+=calc.total;Object.keys(calc.components).forEach((key)=>{subtotal[key]+=calc.components[key];});});const sale=commercial(subtotal.direct);Object.keys(subtotal).forEach((key)=>setText(row,`[data-cost-kind="${key}"]`,subtotal[key]));setText(row,'.brebo-calc-structure-subtotal',subtotal.direct);setText(row,'[data-commercial-kind="general-cost"]',sale.generalCost);setText(row,'[data-commercial-kind="risk"]',sale.risk);setText(row,'[data-commercial-kind="margin"]',sale.margin);setText(row,'[data-commercial-kind="sales-price"]',sale.salesPrice);});
          const totalCommercial=commercial(grand.direct);const adjustment=Number(settings.commercialAdjustment||0);setText(form,'[data-total-kind="direct"]',grand.direct);setText(form,'[data-total-kind="general-cost"]',totalCommercial.generalCost);setText(form,'[data-total-kind="risk"]',totalCommercial.risk);setText(form,'[data-total-kind="margin"]',totalCommercial.margin);setText(form,'[data-total-kind="adjustment"]',adjustment);setText(form,'[data-total-kind="sales-price"]',totalCommercial.salesPrice+adjustment);refreshScenarios(grand.direct);
        };

        const setState=(state)=>{const workbench=form.querySelector('#brebo-calculation-workbench');if(!workbench)return;workbench.dataset.saveState=state;workbench.classList.toggle('is-dirty',state==='dirty');workbench.classList.toggle('is-saving',state==='saving');};
        const autosave=()=>{if(!dirty||saving||saveButton.disabled)return;dirty=false;saving=true;setState('saving');saveButton.click();window.setTimeout(()=>{saving=false;setState('saved');if(dirty)autosave();},900);};
        const schedule=()=>{dirty=true;setState('dirty');refreshClientTotals();window.clearTimeout(timer);timer=window.setTimeout(autosave,650);};
        const collapseStorageKey=`brebo-calc-collapse:${window.location.pathname}`;const collapsed=new Set(JSON.parse(window.localStorage.getItem(collapseStorageKey)||'[]'));
        const applyCollapse=()=>{structureRows.forEach((row)=>{const key=row.dataset.structureKey;if(!key)return;const isCollapsed=collapsed.has(key);row.classList.toggle('is-collapsed',isCollapsed);const toggle=row.querySelector('.brebo-calc-collapse-toggle');if(toggle){toggle.textContent=isCollapsed?'▸':'▾';toggle.setAttribute('aria-expanded',isCollapsed?'false':'true');}if(isCollapsed)childRows(row).forEach((child)=>child.classList.add('is-collapsed-child'));});structureRows.forEach((row)=>{if(collapsed.has(row.dataset.structureKey))return;childRows(row).forEach((child)=>{const hiddenByParent=structureRows.some((parent)=>collapsed.has(parent.dataset.structureKey)&&childRows(parent).includes(child));if(!hiddenByParent)child.classList.remove('is-collapsed-child');});});};
        structureRows.forEach((row)=>{const descriptionCell=row.cells[1];const key=row.dataset.structureKey;if(!descriptionCell||!key||descriptionCell.querySelector('.brebo-calc-collapse-toggle'))return;const button=document.createElement('button');button.type='button';button.className='brebo-calc-collapse-toggle';button.setAttribute('aria-label','In- of uitklappen');button.addEventListener('click',()=>{if(collapsed.has(key))collapsed.delete(key);else collapsed.add(key);window.localStorage.setItem(collapseStorageKey,JSON.stringify(Array.from(collapsed)));grid.querySelectorAll('.is-collapsed-child').forEach((rowEl)=>rowEl.classList.remove('is-collapsed-child'));applyCollapse();});descriptionCell.prepend(button);});

        buildScenarios();refreshClientTotals();applyCollapse();
        grid.addEventListener('input',(event)=>{if(event.target.matches('input.brebo-calc-cell'))schedule();});
        grid.addEventListener('change',(event)=>{if(event.target.matches('select.brebo-calc-cell, input.brebo-calc-cell'))schedule();});
        grid.addEventListener('keydown',(event)=>{if(event.key==='Enter'&&event.target.matches('input.brebo-calc-cell')){event.preventDefault();autosave();const inputs=Array.from(grid.querySelectorAll('input.brebo-calc-cell, select.brebo-calc-cell')).filter((input)=>input.offsetParent!==null);const index=inputs.indexOf(event.target);if(index>=0&&inputs[index+1]){inputs[index+1].focus();inputs[index+1].select?.();}}});
        grid.addEventListener('click',(event)=>{const button=event.target.closest('[data-brebo-confirm-delete]');if(!button)return;if(!window.confirm(button.dataset.breboConfirmDelete||'Deze regel verwijderen?')){event.preventDefault();event.stopImmediatePropagation();}},true);
      });
    }
  };
})(Drupal, once, drupalSettings);
