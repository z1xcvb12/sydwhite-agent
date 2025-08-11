(function () {
  const AIChat = window.AIChat || {};
  window.WPAIAgent = window.WPAIAgent || {};

  AIChat.init = function initChatUI(root) {
    const el = root || document.querySelector('.wpai-chatbox');
    if (!el) return;

    const composer = el.querySelector('.wpai-composer textarea');
    const btnSend  = el.querySelector('.wpai-btn-send');

    // Hint mobile keyboards to show "Send"
    if (composer && !composer.hasAttribute('enterkeyhint')) {
      try { composer.setAttribute('enterkeyhint', 'send'); } catch (e) {}
    }

    // "Enter to send" preference (default true)
    const enterToSend = (typeof window.WPAIAgent.enterToSend === 'boolean')
      ? window.WPAIAgent.enterToSend : true;

    // Track IME composition state to avoid premature sends
    let composing = false;
    composer.addEventListener('compositionstart', function () { composing = true; });
    composer.addEventListener('compositionend', function () { composing = false; });

    // Pressing Enter sends; Shift+Enter inserts newline
    composer.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      if (e.isComposing || composing) return; // IME safety
      if (e.shiftKey) return; // allow newline
      if (!enterToSend) return; // feature toggle
      e.preventDefault();
      if (btnSend && !btnSend.disabled) btnSend.click();
    });

    btnSend.addEventListener('click', function () {
      const text = (composer.value || '').trim();
      if (!text) return;
      sendMessage(text);
      composer.value = '';
    });

    function sendMessage(text) {
      // Placeholder send logic
      const log = el.querySelector('.wpai-log');
      if (log) {
        const item = document.createElement('div');
        item.textContent = text;
        log.appendChild(item);
      }
    }
  };

  window.AIChat = AIChat;
})();
