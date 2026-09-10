let timer=null;
let interval=5000;
self.onmessage = function(e){
  if(e.data && e.data.type==='start'){
    interval = e.data.interval || 5000;
    if(timer) clearInterval(timer);
    timer = setInterval(()=>{ self.postMessage({type:'poll'}); }, interval);
  } else if(e.data && e.data.type==='stop'){
    if(timer) clearInterval(timer);
    timer=null;
  }
};
