(function(){
    const config = window.WPAI_CONFIG || {};

    // Insert: pick an active agent from localized WPAI_AGENT profiles (falls back to "Agent")
    function pickAgent() {
        const profiles = Array.isArray((window.WPAI_CONFIG||{}).agentProfiles)
            ? window.WPAI_CONFIG.agentProfiles
            : [];

        // restore from session if present
        try {
            if (window.sessionStorage) {
                const raw = sessionStorage.getItem('wpAiAgentProfile');
                if (raw) {
                    const obj = JSON.parse(raw);
                    if (obj && obj.name) return obj;
                }
            }
        } catch (e) {}

        let chosen = { name: '', bg: '' };
        if (profiles.length) {
            chosen = profiles[Math.floor(Math.random() * profiles.length)];
        } else {
            // fallback to old agentNames (if still present)
            const list = (window.WPAI_CONFIG && window.WPAI_CONFIG.agentNames) ? window.WPAI_CONFIG.agentNames : [];
            chosen.name = list[Math.floor(Math.random() * list.length)] || 'Agent';
        }
        try {
            if (window.sessionStorage) {
                sessionStorage.setItem('wpAiAgentProfile', JSON.stringify(chosen));
            }
        } catch (e) {}
        return chosen;
    }

    const activeAgent = pickAgent();

    // 1) Put agent name in header (requires markup hook)
    const nameEl = document.querySelector('[data-wpai-agent-name]');
    if (nameEl) nameEl.textContent = activeAgent.name;

    // 2) Apply background image to chat root (optional)
    const rootSel = config.selectors && config.selectors.chatRoot ? config.selectors.chatRoot : '[data-wpai-chat-root]';
    const rootEl = document.querySelector(rootSel);
    if (rootEl && activeAgent.bg) {
      rootEl.style.backgroundImage = `url("${activeAgent.bg}")`;
      rootEl.style.backgroundSize = 'cover';
      rootEl.style.backgroundPosition = 'center center';
    }

    // expose the chosen agent for later use and keep legacy config.agentNames for compatibility
    window.WPAI_ACTIVE_AGENT = activeAgent;
    config.agentNames = [ activeAgent.name ];

    const selectors = config.selectors || {};
    const rootSelector = selectors.chatRoot || '[data-wpai-chat-root]';
    const listSelector = selectors.messageList || '[data-wpai-message-list]';
    const ajax = config.ajaxUrl || config.ajax || '';
    let expiryTimer = null;
    let finished = false;

    function getVisitor(){
        const m = document.cookie.match(/(?:^|; )ai_agent_vid=([^;]+)/);
        return m ? decodeURIComponent(m[1]) : 'anon';
    }

    function getAgentName(){
        try {
            if(window.sessionStorage){
                let n = sessionStorage.getItem('wpAiAgentName');
                if(n){ return n; }
                const list = config.agentNames || [];
                n = list[Math.floor(Math.random()*list.length)] || '';
                sessionStorage.setItem('wpAiAgentName', n);
                return n;
            }
        } catch(e){}
        const list = config.agentNames || [];
        return list[Math.floor(Math.random()*list.length)] || '';
    }

    const agentProfile = pickAgent();
    const agentName = agentProfile.name || 'Agent';
    const visitor = getVisitor();
    const storeKey = 'wpai_conv_' + decodeURIComponent(visitor);

    function init(root){
        if(root.dataset.wpaiInit){ return; }
        root.dataset.wpaiInit = '1';

        const btn = document.createElement('div');
        btn.className = 'ai-agent-button';
        btn.innerHTML = '<svg width="24" height="24"><use href="'+config.assets+'/icons/icons.svg#chat"></use></svg>';

        const win = document.createElement('div');
        win.className = 'ai-agent-window';
        win.innerHTML = '<div class="ai-agent-header">'+agentName+'</div>'+
            '<div class="ai-agent-messages" data-wpai-message-list></div>'+
            '<div class="ai-agent-input"><textarea rows="2"></textarea><button>Send</button></div>';

        if (agentProfile.bg) {
            win.style.backgroundImage = 'url("' + agentProfile.bg + '")';
            win.style.backgroundSize = 'cover';
            win.style.backgroundPosition = 'center center';
            // optional: add slight overlay to keep text legible
            // win.style.backgroundColor = 'rgba(255,255,255,0.85)';
            // win.style.backgroundBlendMode = 'overlay';
        }

        root.appendChild(btn);
        root.appendChild(win);

        let open = false;
        btn.addEventListener('click', function(){
            win.style.display = open ? 'none' : 'flex';
            open = !open;
        });

        const ta = win.querySelector('textarea');
        const send = win.querySelector('button');
        const list = win.querySelector(listSelector);
        let convo = [];

        function persistConversation(){
            const arr = [];
            list.querySelectorAll('.ai-agent-msg').forEach(el => {
                const txt = (el.querySelector('.wpai-msg')?.textContent || '').trim();
                if(!txt){ return; }
                arr.push({
                    role: el.dataset.role || (el.classList.contains('user') ? 'user' : 'assistant'),
                    content: txt,
                    ts: parseInt(el.dataset.ts || Date.now(),10),
                    system: el.classList.contains('system')
                });
            });
            try{ localStorage.setItem(storeKey, JSON.stringify(arr)); }catch(e){}
        }

        function scrollBottom(){ list.scrollTop = list.scrollHeight; }

        function addUser(text, ts){
            const m = document.createElement('div');
            m.className = 'ai-agent-msg user';
            m.dataset.role = 'user';
            m.dataset.ts = ts || Date.now();
            const span = document.createElement('span');
            span.className = 'wpai-msg';
            span.textContent = text;
            m.appendChild(span);
            list.appendChild(m);
            scrollBottom();
            persistConversation();
            return m;
        }

        function renderQuote(q){
            const card = document.createElement('pre');
            card.className = 'ai-agent-quote';
            card.textContent = JSON.stringify(q, null, 2);
            list.appendChild(card);
            scrollBottom();
        }

        function showTyping(){
            const m = document.createElement('div');
            m.className = 'ai-agent-msg bot typing';
            m.dataset.role = 'assistant';
            m.dataset.ts = Date.now();
            m.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> <span class="wpai-msg"><span class="typing-dots"><span></span><span></span><span></span></span></span>';
            list.appendChild(m);
            scrollBottom();
            return m;
        }

        function addBot(text, system, ts){
            const m = document.createElement('div');
            m.className = 'ai-agent-msg bot' + (system ? ' system' : '');
            m.dataset.role = 'assistant';
            m.dataset.ts = ts || Date.now();
            m.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> <span class="wpai-msg"></span>';
            m.querySelector('.wpai-msg').textContent = text;
            list.appendChild(m);
            scrollBottom();
            persistConversation();
        }

        function renderConversation(conv){
            conv.forEach(function(m){
                const text = m.content || m.message || m.text || '';
                if(m.role === 'user'){
                    addUser(text, m.ts);
                } else if(m.system){
                    addBot(text, true, m.ts);
                } else {
                    addBot(text, false, m.ts);
                }
            });
        }

        try{
            const cached = localStorage.getItem(storeKey);
            if(cached){
                convo = JSON.parse(cached) || [];
                renderConversation(convo);
            }
        }catch(e){}

        function clearExpiry(){ if(expiryTimer){ clearTimeout(expiryTimer); expiryTimer = null; } }
        function startExpiry(){
            clearExpiry();
            const minutes = parseInt(config.expiry || 20, 10);
            expiryTimer = setTimeout(function(){ showFinishedBanner(); }, Math.max(1, minutes) * 60 * 1000);
        }

        function showFinishedBanner(existing){
            if(!existing){
                addBot((config.i18n && config.i18n.finished) || 'This chat has finished due to inactivity. Click “Start new chat” to continue.', true);
            }
            const wrap = document.createElement('div');
            wrap.className = 'ai-agent-finished';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ai-agent-btn-new';
            btn.textContent = (config.i18n && config.i18n.startNew) || 'Start new chat';
            btn.addEventListener('click', function(){
                finished = false;
                convo = [];
                localStorage.removeItem(storeKey);
                if(ajax){
                    const fd = new FormData();
                    fd.append('action','ai_agent_end_session');
                    fd.append('nonce', config.nonce || '');
                    fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'});
                }
                wrap.remove();
            });
            wrap.appendChild(btn);
            list.appendChild(wrap);
            scrollBottom();
            finished = true;
            clearExpiry();
        }

        async function hydrate(){
            if(!ajax){ return; }
            const fd = new FormData();
            fd.append('action','ai_agent_get_session');
            fd.append('nonce', config.nonce || '');
            try{
                const res = await fetch(ajax, {method:'POST', body:fd, credentials:'same-origin'});
                const json = await res.json();
                const data = json && json.data ? json.data : {};
                if(Array.isArray(data.conversation)){
                    convo = data.conversation;
                    list.innerHTML = '';
                    renderConversation(convo);
                    persistConversation();
                }
                if(data.status === 'active'){
                    startExpiry();
                } else if(data.status === 'expired'){
                    showFinishedBanner(true);
                }
            } catch(e){}
        }

        async function sendMsg(msg, userTs){
            if(finished){ finished = false; convo = []; localStorage.removeItem(storeKey); }
            startExpiry();
            const typingEl = showTyping();
            ta.disabled = true;
            send.disabled = true;
            let msgSpan;
            try{
                const full = await window.WPAI.sendChatRequest({
                    url: ajax + '?action=ai_agent_chat',
                    headers: { 'Content-Type': 'application/json' },
                    body: { visitor: visitor, message: msg, conversation: convo },
                    onToken: function(tok){
                        if(!msgSpan){
                            typingEl.classList.remove('typing');
                            typingEl.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> <span class="wpai-msg"></span>';
                            msgSpan = typingEl.querySelector('.wpai-msg');
                        }
                        msgSpan.textContent += tok;
                        scrollBottom();
                    },
                    onDone: function(){ startExpiry(); }
                });
                convo.push({role:'user',content:msg,ts:userTs});
                if(full){
                    convo.push({role:'assistant',content:full,ts:parseInt(typingEl.dataset.ts,10)});
                }
                if(!msgSpan){
                    typingEl.classList.remove('typing');
                    typingEl.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> <span class="wpai-msg"></span>';
                    typingEl.querySelector('.wpai-msg').textContent = full;
                }
                try{
                    const q = JSON.parse(full);
                    if(q && q.items){
                        renderQuote(q);
                        if(msgSpan){ msgSpan.textContent = ''; } else { typingEl.querySelector('.wpai-msg').textContent = ''; }
                    }
                }catch(e){}
                persistConversation();
            } catch(e){
                typingEl.remove();
                const err = document.createElement('div');
                err.className = 'ai-agent-msg bot';
                err.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> <span class="wpai-msg">Error</span>';
                list.appendChild(err);
                scrollBottom();
            } finally {
                ta.disabled = false;
                send.disabled = false;
                scrollBottom();
            }
        }

        send.addEventListener('click', function(){
            const msg = ta.value.trim();
            if(!msg){ return; }
            ta.value = '';
            const el = addUser(msg);
            sendMsg(msg, parseInt(el.dataset.ts,10));
        });

        if(config.enterSend){
            ta.addEventListener('keydown', function(e){
                if(e.key === 'Enter' && !e.shiftKey){
                    e.preventDefault();
                    send.click();
                }
            });
        }

        hydrate();
    }

    function boot(){
        document.querySelectorAll(rootSelector).forEach(init);
    }

    boot();
    new MutationObserver(boot).observe(document.documentElement, {childList:true, subtree:true});
})();
