<?php
<div class="wrap">
    <p>
        <button class="button" id="ai-agent-reset">
            <?php _e('Start New Chat', 'wp-ai-agent'); ?>
        </button>
    </p>

    <div id="wp-ai-agent-admin" data-wpai-chat-root></div>

    <script>
    (function(){
        function hardReset(){
            // clear all old admin/test-chat caches
            try {
                if (window.sessionStorage) {
                    // old keys the plugin uses
                    sessionStorage.removeItem('wp_ai_agent_admin');   // legacy
                    sessionStorage.removeItem('wpAiAgentName');       // <-- THIS caused the old name to stick
                    sessionStorage.removeItem('wpAiAgentProfile');    // front-end profile cache
                    // clear cached conversations created by admin demo
                    Object.keys(sessionStorage).forEach(function(k){
                        if (k.indexOf('wpai_conv_') === 0) sessionStorage.removeItem(k);
                    });
                }
            } catch(e){}

            // reset the admin demo root
            var root = document.querySelector('#wp-ai-agent-admin');
            if (root) {
                root.innerHTML = '';
                delete root.dataset.wpaiInit;
            }
        }

        // wire up the "Start New Chat" button
        var btn = document.getElementById('ai-agent-reset');
        if (btn) btn.addEventListener('click', hardReset);

        // also clear once when you open Test Chat (so new names show immediately)
        hardReset();
    })();
    </script>
</div>
