'use strict';
document.querySelectorAll('[data-restpoint-photo]').forEach(root => {
    const image = root.querySelector('img');
    if (!image) return;
    const layer = image.parentElement;
    const viewport = document.createElement('div');
    Object.assign(viewport.style,{overflow:'auto',maxWidth:'100%',maxHeight:'75vh',border:'1px solid #bdcbd7'});
    layer.before(viewport);
    viewport.append(layer);
    const toolbar = document.createElement('div');
    toolbar.setAttribute('aria-label','Zoom en verschuiven');
    viewport.before(toolbar);
    let zoom = 1;
    const status = document.createElement('span');
    function resize() {
        layer.style.width = (zoom*100)+'%';
        layer.style.maxWidth = 'none';
        image.style.width = '100%';
        status.textContent = ' '+Math.round(zoom*100)+'%';
    }
    for (const [label,action] of [['−',()=>{zoom=Math.max(1,zoom/1.25);resize();}],['+',()=>{zoom=Math.min(4,zoom*1.25);resize();}],['Passend',()=>{zoom=1;resize();viewport.scrollTo(0,0);}]]) {
        const button=document.createElement('button');
        button.type='button';button.textContent=label;
        button.addEventListener('click',action);toolbar.append(button);
    }
    toolbar.append(status);
    resize();
    const form = Array.from(root.querySelectorAll('form')).find(f=>f.elements.namedItem('x') && f.elements.namedItem('y'));
    if (!form) return;
    const x = form.elements.namedItem('x');
    const y = form.elements.namedItem('y');
    if (!x || !y) return;
    const preview = document.createElement('span');
    preview.setAttribute('aria-hidden', 'true');
    Object.assign(preview.style, {position:'absolute',border:'2px solid white',background:'#b42318',borderRadius:'50%',width:'20px',height:'20px',transform:'translate(-50%,-50%)',pointerEvents:'none',display:'none'});
    image.parentElement.append(preview);
    function update() {
        if (x.value === '' || y.value === '') return;
        const a = Number(x.value), b = Number(y.value);
        if (!Number.isInteger(a) || !Number.isInteger(b) || Math.min(a,b)<0 || Math.max(a,b)>1000000) { preview.style.display='none'; return; }
        preview.style.left = (a/10000)+'%';
        preview.style.top = (b/10000)+'%';
        preview.style.display='block';
    }
    image.addEventListener('click', event => {
        const rect = image.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        x.value = String(Math.round(Math.max(0,Math.min(1,(event.clientX-rect.left)/rect.width))*1000000));
        y.value = String(Math.round(Math.max(0,Math.min(1,(event.clientY-rect.top)/rect.height))*1000000));
        update();
    });
    const selection=form.elements.namedItem('marker_selection');
    const operation=form.elements.namedItem('marker_operation');
    if (selection && operation) {
        selection.addEventListener('change',()=>{
            const option=selection.selectedOptions[0];
            form.elements.namedItem('marker_id').value=option.value;
            form.elements.namedItem('marker_revision').value=option.dataset.revision||'';
            operation.value=option.value?'move':'create';
            x.value=option.dataset.x||'';
            y.value=option.dataset.y||'';
            preview.style.display='none';
            update();
        });
        form.addEventListener('submit',event=>{
            if (operation.value!=='create' && !selection.value) {
                event.preventDefault();
                selection.focus();
            } else if (operation.value==='remove' && !window.confirm('Deze markering verwijderen?')) {
                event.preventDefault();
            }
        });
    }
    x.addEventListener('input',update);
    y.addEventListener('input',update);
});
