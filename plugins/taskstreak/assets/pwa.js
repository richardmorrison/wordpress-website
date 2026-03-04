if ('serviceWorker' in navigator) {
  window.addEventListener('load', ()=>{
    navigator.serviceWorker.register(TASKSTREAK.assets + 'sw.js');
  });
}
