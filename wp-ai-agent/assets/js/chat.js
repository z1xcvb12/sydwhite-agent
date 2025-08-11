(function($){
    function uuidv4(){return'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g,function(c){var r=Math.random()*16|0,v=c=='x'?r:(r&0x3|0x8);return v.toString(16);});}
    var vid=document.cookie.match(/ai_agent_vid=([^;]+)/);if(!vid){vid=uuidv4();document.cookie='ai_agent_vid='+vid+';path=/;max-age='+(365*24*60*60);}else{vid=vid[1];}
    var root=document.getElementById('wp-ai-agent-root');
    if(!root){root=document.getElementById('wp-ai-agent-admin');}
    if(!root)return;
    var names=(window.wpaiAgentNames&&Array.isArray(window.wpaiAgentNames)&&window.wpaiAgentNames.length)?window.wpaiAgentNames:["Jack Wilson","Olivia Nguyen","Liam O'Connor","Chloe Smith","Noah Patel"];
    var idx=sessionStorage.getItem('wpaiAgentNameIndex');
    if(idx===null){idx=Math.floor(Math.random()*names.length);sessionStorage.setItem('wpaiAgentNameIndex',String(idx));}else{idx=Number(idx)%names.length;}
    var agentName=names[idx];
    var btn=document.createElement('div');btn.className='ai-agent-button';btn.innerHTML='<svg width="24" height="24"><use href="'+wpAiAgent.assets+'/icons/icons.svg#chat"/></svg>';
    var win=document.createElement('div');win.className='ai-agent-window';win.innerHTML='<div class="ai-agent-header"></div><div class="ai-agent-messages"></div><div class="ai-agent-input"><textarea rows="2"></textarea><button>Send</button></div>';
    root.appendChild(btn);root.appendChild(win);
    win.querySelector('.ai-agent-header').textContent=agentName;
    var messages=win.querySelector('.ai-agent-messages');
    var typing=messages.querySelector('.wpai-typing');
    if(!typing){typing=document.createElement('div');typing.className='wpai-typing';typing.setAttribute('role','status');typing.setAttribute('aria-live','polite');typing.innerHTML='<span class="wpai-typing__label"></span><span class="wpai-dots"><span class="wpai-dot">•</span><span class="wpai-dot">•</span><span class="wpai-dot">•</span></span>';messages.appendChild(typing);}
    var labelEl=typing.querySelector('.wpai-typing__label');
    var open=false;btn.addEventListener('click',function(){win.style.display=open?'none':'flex';open=!open;});
    var ta=win.querySelector('textarea');var send=win.querySelector('button');
    function scrollToBottom(){messages.scrollTop=messages.scrollHeight;}
    function addMsg(role,text){var m=document.createElement('div');m.className='ai-agent-msg '+role;m.textContent=text;messages.insertBefore(m,typing);scrollToBottom();}
    function renderQuote(q){var card=document.createElement('pre');card.className='ai-agent-quote';card.textContent=JSON.stringify(q,null,2);messages.insertBefore(card,typing);}
    function showTyping(){labelEl.textContent=agentName+' is typing';typing.classList.add('active');scrollToBottom();}
    function hideTyping(){typing.classList.remove('active');}
    send.addEventListener('click',function(){var msg=ta.value.trim();if(!msg)return;ta.value='';addMsg('user',msg);sendMsg(msg);});
    if(wpAiAgent.enterSend){ta.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();send.click();}});}
    var convo=[];
    function sendMsg(msg){var data=new FormData();data.append('action','ai_agent_chat');data.append('visitor',vid);data.append('message',msg);data.append('conversation',JSON.stringify(convo));showTyping();fetch(wpAiAgent.ajax,{method:'POST',body:data}).then(function(res){if(!res.body||!res.body.getReader){return res.text().then(function(out){convo.push({role:'user',content:msg});convo.push({role:'assistant',content:out});try{var q=JSON.parse(out);if(q.items){renderQuote(q);out='';}}catch(e){}addMsg('bot',out);hideTyping();});}var reader=res.body.getReader();var decoder=new TextDecoder();var out='';function read(){reader.read().then(function(r){if(r.done){convo.push({role:'user',content:msg});convo.push({role:'assistant',content:out});try{var q=JSON.parse(out);if(q.items){renderQuote(q);out='';}}catch(e){}addMsg('bot',out);hideTyping();return;}var str=decoder.decode(r.value);str.split('\n').forEach(function(line){if(line.startsWith('data:')){var payload=line.replace('data:','').trim();if(payload==='[DONE]'){return;}try{var j=JSON.parse(payload);if(j.choices&&j.choices[0].delta&&j.choices[0].delta.content){out+=j.choices[0].delta.content;}}catch(e){}}});read();}).catch(function(){hideTyping();});}read();}).catch(function(){hideTyping();});}
})(jQuery);
