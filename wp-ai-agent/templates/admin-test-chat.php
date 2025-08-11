<div id="wp-ai-agent-admin"></div>
<p><button class="button" id="ai-agent-reset"><?php _e( 'Start New Chat', 'wp-ai-agent' ); ?></button></p>
<script>
(function(){
    document.getElementById('ai-agent-reset').addEventListener('click', function(){
        if(window.sessionStorage){sessionStorage.removeItem('wp_ai_agent_admin');}
        document.querySelector('#wp-ai-agent-admin').innerHTML='';
    });
})();
</script>
