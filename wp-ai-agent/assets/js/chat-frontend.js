(function(){
    const config = window.WPAI_CONFIG || {};
    const selectors = config.selectors || {};
    const rootSelector = selectors.chatRoot || '[data-wpai-chat-root]';
    const listSelector = selectors.messageList || '[data-wpai-message-list]';
    let expiryTimer = null;
    let finished = false;
    let allowNew = false;

    function uuidv4(){
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,function(c){
            const r = Math.random()*16|0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function getVisitor(){
        const m = document.cookie.match(/ai_agent_vid=([^;]+)/);
        if(m){ return m[1]; }
        const id = uuidv4();
        document.cookie = 'ai_agent_vid=' + id + ';path=/;max-age=' + (365*24*60*60);
        return id;
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
        const storageKey = 'wpai_conv_' + visitor;
        let convo = [];

        function scrollBottom(){ list.scrollTop = list.scrollHeight; }

        function persist(){ try{ localStorage.setItem(storageKey, JSON.stringify(convo)); }catch(e){} }

        function hydrateLocal(){
            try{ const raw = localStorage.getItem(storageKey); if(raw){ const arr = JSON.parse(raw); if(Array.isArray(arr) && arr.length){ convo = arr; renderConversation(convo); } } }catch(e){}
        }

        function clearMessages(){ list.innerHTML = ''; }

        function addUser(text, ts){
            const m = document.createElement('div');
            m.className = 'ai-agent-msg user';
            m.textContent = text;
            m.setAttribute('data-role','user');
            if(ts){ m.setAttribute('data-ts', ts); }
            list.appendChild(m);
            scrollBottom();
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

        function addBot(text, system, ts){
            const m = document.createElement('div');
            m.className = 'ai-agent-msg bot' + (system ? ' system' : '');
            m.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> ' + text;
            m.setAttribute('data-role','assistant');
            if(ts){ m.setAttribute('data-ts', ts); }
            list.appendChild(m);
            scrollBottom();
        }

        function renderConversation(conv){
            conv.forEach(function(m){
                if(m.role === 'user'){
                    addUser(m.content, m.ts);
                } else if(m.system){
                    addBot(m.content, true, m.ts);
                } else {
                    addBot(m.content, false, m.ts);
                }
            });
        }

        function clearExpiry(){ if(expiryTimer){ clearTimeout(expiryTimer); expiryTimer = null; } }
        function startExpiry(){
            clearExpiry();
            const minutes = parseInt(config.expiry || 20, 10);
            expiryTimer = setTimeout(function(){ showFinishedBanner(); }, Math.max(1, minutes) * 60 * 1000);
        }

        function showFinishedBanner(existing){
            if(finished){ return; }
            let msg;
            if(!existing){
                msg = (config.i18n && config.i18n.finished) || 'This chat has finished due to inactivity. Click “Start new chat” to continue.';
                addBot(msg, true, Math.floor(Date.now()/1000));
                convo.push({role:'assistant', content:msg, ts:Math.floor(Date.now()/1000), system:true});
                persist();
            }
            const wrap = document.createElement('div');
            wrap.className = 'ai-agent-finished';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ai-agent-btn-new';
            btn.textContent = (config.i18n && config.i18n.startNew) || 'Start new chat';
            btn.addEventListener('click', function(){
                allowNew = true;
                if(config.ajax){
                    const fd = new FormData();
                    fd.append('action','ai_agent_end_session');
                    fd.append('nonce', config.nonce || '');
                    fetch(config.ajax, {method:'POST', body:fd, credentials:'same-origin'});
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
            if(!config.ajax){ return; }
            const fd = new FormData();
            fd.append('action','ai_agent_get_session');
            fd.append('nonce', config.nonce || '');
            try{
                const res = await fetch(config.ajax, {method:'POST', body:fd, credentials:'same-origin'});
                const json = await res.json();
                const data = json && json.data ? json.data : {};
                if(Array.isArray(data.conversation)){
                    const serverConv = data.conversation;
                    if(JSON.stringify(serverConv) !== JSON.stringify(convo)){
                        convo = serverConv;
                        clearMessages();
                        renderConversation(convo);
                    }
                    persist();
                }
                if(data.status === 'active'){
                    startExpiry();
                } else if(data.status === 'expired'){
                    showFinishedBanner(true);
                }
            } catch(e){}
        }

        async function sendMsg(msg){
            if(finished || allowNew){
                convo = [];
                persist();
                finished = false;
                allowNew = false;
            }
            startExpiry();
            const typingEl = showTyping();
            ta.disabled = true;
            send.disabled = true;
            const prevConv = convo.slice();
            const tsUser = Math.floor(Date.now()/1000);
            addUser(msg, tsUser);
            convo.push({role:'user', content:msg, ts:tsUser});
            persist();
            let textNode;
            let firstChunk = true;
            try{
                const full = await window.WPAI.sendChatRequest({
                    url: config.ajax + '?action=ai_agent_chat',
                    headers: { 'Content-Type': 'application/json' },
                    body: { visitor: visitor, message: msg, conversation: prevConv },
                    onToken: function(tok){
                        if(firstChunk){ firstChunk = false; startExpiry(); }
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
                const tsAssist = Math.floor(Date.now()/1000);
                convo.push({role:'assistant', content:full, ts:tsAssist});
                persist();
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
            } catch(e){
                typingEl.remove();
                const err = document.createElement('div');
                err.className = 'ai-agent-msg bot';
                err.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> Error';
                list.appendChild(err);
                scrollBottom();
                startExpiry();
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

        hydrateLocal();
        hydrate();
    }

    function boot(){
        document.querySelectorAll(rootSelector).forEach(init);
    }

    boot();
    new MutationObserver(boot).observe(document.documentElement, {childList:true, subtree:true});
})();
