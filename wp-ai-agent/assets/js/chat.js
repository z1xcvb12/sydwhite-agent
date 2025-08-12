(function(){
    const cfg = window.wpaiAgent || {};
    const ajaxUrl = cfg.ajaxUrl;
    const nonce = cfg.nonce;
    const vid = (document.cookie.match(/(?:^|; )ai_agent_vid=([^;]+)/)||[])[1] || 'anon';
    const storeKey = 'wpai_conv_' + decodeURIComponent(vid);
    const list = document.querySelector('[data-wpai-message-list]');
    if(!list){ return; }

    function render(conv){
        list.innerHTML = '';
        conv.forEach(function(m){
            const el = document.createElement('div');
            el.className = 'wpai-msg';
            el.dataset.role = m.role;
            el.dataset.ts = m.ts || Date.now();
            if(m.system){ el.dataset.system = '1'; }
            el.textContent = m.content || '';
            list.appendChild(el);
        });
    }

    function save(){
        const conv = [];
        list.querySelectorAll('.wpai-msg').forEach(function(el){
            conv.push({
                role: el.dataset.role || 'assistant',
                content: el.textContent || '',
                ts: parseInt(el.dataset.ts,10) || Date.now(),
                system: !!el.dataset.system
            });
        });
        try{ localStorage.setItem(storeKey, JSON.stringify(conv)); }catch(e){}
    }

    let localConv = [];
    try{ localConv = JSON.parse(localStorage.getItem(storeKey) || '[]'); }catch(e){}
    if(Array.isArray(localConv) && localConv.length){
        render(localConv);
    }

    if(ajaxUrl && nonce){
        const fd = new FormData();
        fd.append('action','ai_agent_get_session');
        fd.append('nonce', nonce);
        fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
            .then(r=>r.json())
            .then(res=>{
                const data = res && res.data ? res.data : {};
                if(Array.isArray(data.conversation) && data.conversation.length){
                    render(data.conversation);
                    save();
                }
            });
    }

    const obs = new MutationObserver(save);
    obs.observe(list,{childList:true});
})();
