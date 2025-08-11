(function($){
    function uuidv4(){return'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,function(c){var r=Math.random()*16|0,v=c=='x'?r:(r&0x3|0x8);return v.toString(16);});}
    var vid=document.cookie.match(/ai_agent_vid=([^;]+)/);if(!vid){vid=uuidv4();document.cookie='ai_agent_vid='+vid+';path=/;max-age='+(365*24*60*60);}else{vid=vid[1];}
    var root=document.getElementById('wp-ai-agent-root');
    if(!root){root=document.getElementById('wp-ai-agent-admin');}
    if(!root)return;
    var btn=document.createElement('div');btn.className='ai-agent-button';btn.innerHTML='<svg width="24" height="24"><use href="'+wpAiAgent.assets+'/icons/icons.svg#chat"/></svg>';
    var win=document.createElement('div');win.className='ai-agent-window';win.innerHTML='<div class="ai-agent-header">AI Agent</div><div class="ai-agent-messages"></div><div class="ai-agent-input"><textarea rows="2"></textarea><button>Send</button></div>';
    root.appendChild(btn);root.appendChild(win);
    var open=false;btn.addEventListener('click',function(){win.style.display=open?'none':'flex';open=!open;});
    var ta=win.querySelector('textarea');var send=win.querySelector('button');var isComposing=false,isSending=false;
    function addMsg(role,text){var m=document.createElement('div');m.className='ai-agent-msg '+role;m.textContent=text;win.querySelector('.ai-agent-messages').appendChild(m);win.querySelector('.ai-agent-messages').scrollTop=999999;}
    function renderQuote(q){var card=document.createElement('pre');card.className='ai-agent-quote';card.textContent=JSON.stringify(q,null,2);win.querySelector('.ai-agent-messages').appendChild(card);}
    function doSend(){if(isSending)return;var msg=ta.value.trim();if(!msg)return;isSending=true;ta.disabled=true;send.disabled=true;ta.value='';addMsg('user',msg);sendMsg(msg).finally(function(){isSending=false;ta.disabled=false;send.disabled=false;ta.focus();});}
    send.addEventListener('click',doSend);
    ta.addEventListener('compositionstart',function(){isComposing=true;});
    ta.addEventListener('compositionend',function(){isComposing=false;});
    if(wpAiAgent.enterSend){ta.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey&&!e.ctrlKey&&!e.altKey&&!e.metaKey&&!isComposing){e.preventDefault();doSend();}});}
    var convo=[];
    function sendMsg(msg){var data=new FormData();data.append('action','ai_agent_chat');data.append('visitor',vid);data.append('message',msg);data.append('conversation',JSON.stringify(convo));return fetch(wpAiAgent.ajax,{method:'POST',body:data}).then(function(res){var reader=res.body.getReader();var decoder=new TextDecoder();var out='';return new Promise(function(resolve){function read(){reader.read().then(function(r){if(r.done){convo.push({role:'user',content:msg});convo.push({role:'assistant',content:out});try{var q=JSON.parse(out);if(q.items){renderQuote(q);out='';}}catch(e){}addMsg('bot',out);resolve();return;}var str=decoder.decode(r.value);str.split('\n').forEach(function(line){if(line.startsWith('data:')){var payload=line.replace('data:','').trim();if(payload==='[DONE]'){return;}try{var j=JSON.parse(payload);if(j.choices&&j.choices[0].delta&&j.choices[0].delta.content){out+=j.choices[0].delta.content;}}catch(e){}}});read();});}read();});});}
})(jQuery);
