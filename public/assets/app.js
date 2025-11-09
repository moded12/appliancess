// Fade-in cards after image load
document.addEventListener('DOMContentLoaded', ()=>{
  document.querySelectorAll('.fade-in').forEach(el=>{
    const img = el.querySelector('img'); const done=()=>el.classList.add('loaded');
    if(img){ img.complete?done():(img.addEventListener('load',done,{once:true}),img.addEventListener('error',done,{once:true})); }
    else done();
  });

  // Drag-scroll categories
  const tray=document.getElementById('catTray');
  if(tray){
    let isDown=false,startX=0,scrollLeft=0;
    const start=e=>{isDown=true;startX=(e.touches?e.touches[0].pageX:e.pageX)-tray.offsetLeft;scrollLeft=tray.scrollLeft;};
    const move=e=>{if(!isDown)return;e.preventDefault();const x=(e.touches?e.touches[0].pageX:e.pageX)-tray.offsetLeft;tray.scrollLeft=scrollLeft-(x-startX);};
    const end=()=>isDown=false;
    tray.addEventListener('mousedown',start);tray.addEventListener('mousemove',move);tray.addEventListener('mouseleave',end);tray.addEventListener('mouseup',end);
    tray.addEventListener('touchstart',start,{passive:true});tray.addEventListener('touchmove',move,{passive:false});tray.addEventListener('touchend',end);
    tray.addEventListener('wheel',e=>{if(Math.abs(e.deltaY)>Math.abs(e.deltaX)){tray.scrollLeft+=e.deltaY;e.preventDefault();}},{passive:false});
  }

  // Switch main media on product page
  const thumbs=document.querySelectorAll('[data-main-src]'); const main=document.getElementById('mainMedia');
  if(thumbs.length && main){
    thumbs.forEach(t=>t.addEventListener('click',()=>{
      thumbs.forEach(x=>x.classList.remove('active')); t.classList.add('active');
      const type=t.dataset.type, src=t.dataset.mainSrc;
      if(type==='video'){ main.innerHTML=`<div class="media-16x9"><video src="${src}" autoplay muted loop playsinline></video></div>`; }
      else{ main.innerHTML=`<img src="${src}" class="img-fluid rounded-4 shadow" alt="">`; }
      window.scrollTo({top:0,behavior:'smooth'});
    }));
  }
});
