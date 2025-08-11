export async function sendChatRequest({url, headers = {}, body = {}, streamPreferred = true, onToken, onDone}) {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort('timeout'), 90_000);
  let full = '';
  try {
    const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify(body), signal: ctrl.signal });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const ctype = (res.headers.get('content-type') || '').toLowerCase();
    if (streamPreferred && (res.body && (ctype.includes('text/event-stream') || ctype.includes('application/json')))) {
      const reader = res.body.getReader();
      const decoder = new TextDecoder();
      let done, value, buf = '';
      while (true) {
        ({done, value} = await reader.read());
        if (done) break;
        buf += decoder.decode(value, {stream:true});
        let idx;
        while ((idx = buf.indexOf('\n')) >= 0) {
          const line = buf.slice(0, idx).trim();
          buf = buf.slice(idx+1);
          if (!line) continue;
          const dataLine = line.startsWith('data:') ? line.slice(5).trim() : line;
          if (dataLine === '[DONE]') { onDone?.(full); return full; }
          try {
            const obj = JSON.parse(dataLine);
            const choice = obj.choices && obj.choices[0];
            const delta = choice && (choice.delta?.content ?? choice.message?.content ?? '');
            if (delta) {
              full += delta;
              onToken?.(delta);
            }
          } catch {}
        }
      }
      try {
        const obj = JSON.parse(buf);
        const content = obj?.choices?.[0]?.message?.content ?? '';
        if (content) { full += content; onToken?.(content); }
      } catch {}
      onDone?.(full);
      return full;
    }
    const json = await res.json();
    const content = json?.choices?.[0]?.message?.content ?? json?.message?.content ?? '';
    if (content) {
      full += content;
      onToken?.(content);
    }
    onDone?.(full);
    return full;
  } finally {
    clearTimeout(t);
  }
}
