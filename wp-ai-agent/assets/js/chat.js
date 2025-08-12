(function(){
    const config = window.WPAIAgent || {};
    const selectors = config.selectors || {};
    const rootSelector = selectors.chatRoot || '[data-wpai-chat-root]';
    const listSelector = selectors.messageList || '[data-wpai-message-list]';
    let expiryTimer = null, allowNewSession = false;
    let finished = false;

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
        let convo = [];

        function persistConversation(){
            try{ localStorage.setItem('wpai_conversation', JSON.stringify(convo)); }catch(e){}
        }

        function scrollBottom(){ list.scrollTop = list.scrollHeight; }

        function addUser(text){
            const m = document.createElement('div');
            m.className = 'ai-agent-msg user';
            m.textContent = text;
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

        function addBot(text, system){
            const m = document.createElement('div');
            m.className = 'ai-agent-msg bot' + (system ? ' system' : '');
            m.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> ' + text;
            list.appendChild(m);
            scrollBottom();
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

        function clearExpiryTimer(){ if(expiryTimer){ clearTimeout(expiryTimer); expiryTimer = null; } }
        function startExpiryTimer(){
            clearExpiryTimer();
            const m = parseInt(config.expiryMinutes,10);
            const ms = (isFinite(m) && m>0 ? m : 20) * 60 * 1000;
            expiryTimer = setTimeout(showFinishedBanner, ms);
        }

        function showFinishedBanner(){
            if(finished){ return; }
            const msg = (config.i18n && config.i18n.finished) || 'This chat has finished due to inactivity. Click “Start new chat” to continue.';
            const wrap = document.createElement('div');
            wrap.className = 'ai-agent-msg bot system wpai-finished';
            const span = document.createElement('span');
            span.textContent = msg;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'wpai-btn-start-new';
            btn.textContent = (config.i18n && config.i18n.startNew) || 'Start new chat';
            btn.addEventListener('click', function(){
                const fd = new FormData();
                fd.append('action','ai_agent_end_session');
                fd.append('nonce', config.nonce || '');
                fetch(config.ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'});
                allowNewSession = true;
                clearExpiryTimer();
                persistConversation();
            });
            wrap.appendChild(span);
            wrap.appendChild(btn);
            list.appendChild(wrap);
            scrollBottom();
            convo.push({role:'assistant', content:msg, system:true, ts:Math.floor(Date.now()/1000)});
            persistConversation();
            finished = true;
        }

        async function hydrate(){
            if(!config.ajaxUrl){ return; }
            const fd = new FormData();
            fd.append('action','ai_agent_get_session');
            fd.append('nonce', config.nonce || '');
            try{
                const res = await fetch(config.ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'});
                const json = await res.json();
                const data = json && json.data ? json.data : {};
                if(Array.isArray(data.conversation)){
                    convo = data.conversation;
                    renderConversation(convo);
                    persistConversation();
                }
                if(data.status === 'active'){
                    startExpiryTimer();
                } else if(data.status === 'expired'){
                    showFinishedBanner();
                }
            } catch(e){}
        }

        async function sendMsg(msg){
            if(finished){
                if(!allowNewSession){ return; }
                finished = false;
                allowNewSession = false;
                convo = [];
            }
            startExpiryTimer();
            const typingEl = showTyping();
            ta.disabled = true;
            send.disabled = true;
            let textNode;
            try{
                const full = await window.WPAI.sendChatRequest({
                    url: config.ajaxUrl + '?action=ai_agent_chat',
                    headers: { 'Content-Type': 'application/json' },
                    body: { visitor: visitor, message: msg, conversation: convo },
                    onToken: function(tok){
                        if(!textNode){
                            typingEl.classList.remove('typing');
                            typingEl.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> ';
                            textNode = document.createTextNode(tok);
                            typingEl.appendChild(textNode);
                            startExpiryTimer();
                        } else {
                            textNode.textContent += tok;
                        }
                        scrollBottom();
                    },
                    onDone: function(){ startExpiryTimer(); }
                });
                convo.push({role:'user',content:msg});
                convo.push({role:'assistant',content:full});
                persistConversation();
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
            } finally {
                startExpiryTimer();
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
