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

        function finishTyping(el, text){
            try{
                const q = JSON.parse(text);
                if(q && q.items){
                    renderQuote(q);
                    text = '';
                }
            }catch(e){}
            el.classList.remove('typing');
            el.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> '+text;
            scrollBottom();
        }

        function sendMsg(msg){
            const typingEl = showTyping();
            const data = new FormData();
            data.append('action', 'ai_agent_chat');
            data.append('visitor', visitor);
            data.append('message', msg);
            data.append('conversation', JSON.stringify(convo));

            fetch(config.ajax, {method:'POST', body:data}).then(function(res){
                const ct = res.headers.get('Content-Type') || '';
                if(ct.indexOf('text/event-stream') !== -1 && res.body){
                    const reader = res.body.getReader();
                    const decoder = new TextDecoder();
                    let out = '';
                    function read(){
                        reader.read().then(function(r){
                            if(r.done){
                                convo.push({role:'user',content:msg});
                                convo.push({role:'assistant',content:out});
                                finishTyping(typingEl, out);
                                return;
                            }
                            const str = decoder.decode(r.value);
                            str.split('\n').forEach(function(line){
                                if(line.startsWith('data:')){
                                    const payload = line.replace('data:','').trim();
                                    if(payload === '[DONE]'){ return; }
                                    try{
                                        const j = JSON.parse(payload);
                                        const d = j.choices && j.choices[0] && j.choices[0].delta && j.choices[0].delta.content;
                                        if(d){ out += d; }
                                    }catch(e){}
                                }
                            });
                            typingEl.classList.remove('typing');
                            typingEl.innerHTML = '<span class="ai-agent-name">'+agentName+':</span> '+out;
                            scrollBottom();
                            read();
                        });
                    }
                    read();
                } else {
                    res.text().then(function(out){
                        convo.push({role:'user',content:msg});
                        convo.push({role:'assistant',content:out});
                        finishTyping(typingEl, out);
                    });
                }
            }).catch(function(){ typingEl.remove(); });
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
    }

    function boot(){
        document.querySelectorAll(rootSelector).forEach(init);
    }

    boot();
    new MutationObserver(boot).observe(document.documentElement, {childList:true, subtree:true});
})();
