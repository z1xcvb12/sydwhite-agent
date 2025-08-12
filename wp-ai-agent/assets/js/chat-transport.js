(function(){
  async function sendChatRequest({url, headers={}, body, streamPreferred=true, onToken, onDone}) {
    const ctrl = new AbortController();
    const t = setTimeout(() => ctrl.abort('timeout'), 90_000);
    let full = '';
    try {
      const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify(body), signal: ctrl.signal });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const ctype = (res.headers.get('content-type') || '').toLowerCase();
      if (streamPreferred && res.body && (ctype.includes('text/event-stream') || ctype.includes('application/json'))) {
        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        let buf = '';
        let finished = false;
        while (!finished) {
          const {done, value} = await reader.read();
          if (done) break;
          buf += decoder.decode(value, {stream:true});
          const parts = buf.split('\n');
          buf = parts.pop();
          for (const line of parts) {
            if (!line.startsWith('data:')) continue;
            const payload = line.slice(5).trim();
            if (payload === '[DONE]') { finished = true; break; }
            try {
              const j = JSON.parse(payload);
              const delta = j.choices?.[0]?.delta?.content || '';
              if (delta) { full += delta; onToken && onToken(delta); }
            } catch {}
          }
        }
        onDone && onDone(full);
        return full;
      }
      const json = await res.json();
      const content = json?.choices?.[0]?.message?.content ?? json?.message?.content ?? '';
      if (content) {
        full += content;
        onToken && onToken(content);
      }
      onDone && onDone(full);
      return full;
    } finally {
      clearTimeout(t);
    }
  }
  window.WPAI = window.WPAI || {};
  window.WPAI.sendChatRequest = sendChatRequest;
})();
