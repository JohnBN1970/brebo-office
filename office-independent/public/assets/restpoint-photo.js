'use strict';
document.querySelectorAll('[data-restpoint-photo]').forEach(root => {
    const image = root.querySelector('img');
    const form = root.querySelector('form');
    if (!image || !form) return;
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
    x.addEventListener('input',update);
    y.addEventListener('input',update);
});
