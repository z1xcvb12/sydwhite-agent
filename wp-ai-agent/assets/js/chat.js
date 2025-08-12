(function(){
    const config = window.WPAI_CONFIG || {};
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

    const agentName = getAgentName();
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

        function saveLocal(){
            const arr = [];
            list.querySelectorAll('.ai-agent-msg').forEach(el => {
                let text = el.textContent;
                const prefix = agentName + ':';
                if((el.dataset.role||'') === 'assistant' && text.startsWith(prefix)){
                    text = text.slice(prefix.length).trim();
                }
                arr.push({
                    role: el.dataset.role || (el.classList.contains('user') ? 'user' : 'assistant'),
                    content: text,
                    ts: parseInt(el.dataset.ts || Date.now(),10),
                    system: el.classList.contains('system')
                });
            });
            try{ localStorage.setItem(storeKey, JSON.stringify(arr)); }catch(e){}
        }

        function scrollBottom(){ list.scrollTop = list.scrollHeight; }

        function addUser(text){
            const m = document.createElement('div');
            m.className = 'ai-agent-msg user';
            m.dataset.role = 'user';
            m.dataset.ts = Date.now();
            m.textContent = text;
            list.appendChild(m);
            scrollBottom();
            saveLocal();
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
            m.innerHTML = '<span class="ai-agent-name">'+agentName+'</span> is typing <span class="typing-dots"><span></span><span></span><span></span></span>';
            list.appendChild(m);
            scrollBottom();
            return m;
        }

        function addBot(text, system){
            const m = document.createElement('div');
            m.className = 'ai-agent-msg bot' + (system ? ' system' : '');
            m.dataset.role = 'assistant';
            m.dataset.ts = Date.now();
            m.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> ' + text;
            list.appendChild(m);
            scrollBottom();
            saveLocal();
        }

        function renderConversation(conv){
            conv.forEach(function(m){
                if(m.role === 'user'){
                    addUser(m.content);
                } else if(m.system){
                    addBot(m.content, true);
                } else {
                    addBot(m.content);
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
                    saveLocal();
                }
                if(data.status === 'active'){
                    startExpiry();
                } else if(data.status === 'expired'){
                    showFinishedBanner(true);
                }
            } catch(e){}
        }

        async function sendMsg(msg){
            if(finished){ finished = false; convo = []; localStorage.removeItem(storeKey); }
            startExpiry();
            const typingEl = showTyping();
            ta.disabled = true;
            send.disabled = true;
            let textNode;
            try{
                const full = await window.WPAI.sendChatRequest({
                    url: ajax + '?action=ai_agent_chat',
                    headers: { 'Content-Type': 'application/json' },
                    body: { visitor: visitor, message: msg, conversation: convo },
                    onToken: function(tok){
                        if(!textNode){
                            typingEl.classList.remove('typing');
                            typingEl.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> ';
                            textNode = document.createTextNode(tok);
                            typingEl.appendChild(textNode);
                        } else {
                            textNode.textContent += tok;
                        }
                        scrollBottom();
                    },
                    onDone: function(){ startExpiry(); }
                });
                convo.push({role:'user',content:msg});
                convo.push({role:'assistant',content:full});
                if(!textNode){
                    typingEl.classList.remove('typing');
                    typingEl.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> '+full;
                }
                try{
                    const q = JSON.parse(full);
                    if(q && q.items){
                        renderQuote(q);
                        if(textNode){ textNode.textContent = ''; }
                    }
                }catch(e){}
                saveLocal();
            } catch(e){
                typingEl.remove();
                const err = document.createElement('div');
                err.className = 'ai-agent-msg bot';
                err.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> Error';
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
            addUser(msg);
            sendMsg(msg);
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
