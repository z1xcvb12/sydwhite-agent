(function(){
    const config = window.WPAI_CONFIG || {};
    const selectors = config.selectors || {};
    const rootSelector = selectors.chatRoot || '[data-wpai-chat-root]';
    const listSelector = selectors.messageList || '[data-wpai-message-list]';

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
        let expiryTimer = null;
        let allowNew = false;

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
            m.innerHTML = '<span class="ai-agent-name">'+agentName+(system?'':'')+':</span> '+text;
            list.appendChild(m);
            scrollBottom();
        }

        function renderConv(arr){
            arr.forEach(function(m){
                if(m.role === 'user'){ addUser(m.content); }
                else { addBot(m.content, m.system); }
            });
        }

        function clearTimer(){ if(expiryTimer){ clearTimeout(expiryTimer); expiryTimer=null; } }
        function startTimer(){
            clearTimer();
            const mins = parseInt(config.expiry || 20, 10);
            expiryTimer = setTimeout(function(){ showFinished(true); }, Math.max(1, mins)*60*1000);
        }

        function showFinished(addMsg){
            if(addMsg){ addBot((config.i18n&&config.i18n.finished)||'This chat has finished due to inactivity. Click “Start new chat” to continue.', true); }
            const wrap = document.createElement('div');
            wrap.className = 'ai-agent-finish';
            const btn = document.createElement('button');
            btn.textContent = (config.i18n&&config.i18n.startNew)||'Start new chat';
            btn.addEventListener('click', function(){
                allowNew = true;
                clearTimer();
                try{
                    const fd = new FormData();
                    fd.append('action','ai_agent_end_session');
                    fd.append('nonce', config.nonce || '');
                    fetch(config.ajax, {method:'POST', body:fd, credentials:'same-origin'});
                }catch(e){}
            });
            wrap.appendChild(btn);
            list.appendChild(wrap);
            scrollBottom();
        }

        function bootstrap(){
            const fd = new FormData();
            fd.append('action','ai_agent_get_session');
            fd.append('nonce', config.nonce || '');
            fetch(config.ajax, {method:'POST', body:fd, credentials:'same-origin'})
                .then(r=>r.json())
                .then(res=>{
                    if(!res || !res.success){ return; }
                    const data = res.data || {};
                    if(Array.isArray(data.conversation)){
                        convo = data.conversation;
                        renderConv(convo);
                    }
                    if(data.status === 'active'){ startTimer(); }
                    if(data.status === 'expired'){ showFinished(false); }
                })
                .catch(()=>{});
        }

        async function sendMsg(msg){
            const typingEl = showTyping();
            ta.disabled = true;
            send.disabled = true;
            let textNode;
            const newFlag = allowNew ? 1 : 0; allowNew = false;
            startTimer();
            try{
                const full = await window.WPAI.sendChatRequest({
                    url: config.ajax + '?action=ai_agent_chat',
                    headers: { 'Content-Type': 'application/json' },
                    body: { visitor: visitor, message: msg, new_session: newFlag, nonce: config.nonce },
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
                    onDone: function(){ startTimer(); }
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
        bootstrap();
    }

    function boot(){
        document.querySelectorAll(rootSelector).forEach(init);
    }

    boot();
    new MutationObserver(boot).observe(document.documentElement, {childList:true, subtree:true});
})();
