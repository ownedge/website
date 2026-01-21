<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue';
import SoundManager from '~/utils/sfx/SoundManager';
import { chatStore } from '~/stores/chatStore';

const message = ref('');
const messageInput = ref(null);
const logContainer = ref(null);
const isValidNickname = computed(() => chatStore.nickname.trim().length >= 3);

const focusInput = async () => {
    // Retry focus a few times to account for rendering/animation
    for (let i = 0; i < 3; i++) {
        await nextTick();
        if (messageInput.value) {
            messageInput.value.focus();
            scrollToBottom();
            break;
        }
        await new Promise(r => setTimeout(r, 50));
    }
};

const scrollToBottom = async () => {
    // We wait for two ticks to be absolutely sure the DOM has updated
    // and the container height has been recalculated.
    await nextTick();
    await nextTick();
    if (logContainer.value) {
        logContainer.value.scrollTop = logContainer.value.scrollHeight;
    }
};

const handleSend = async () => {
    if (!message.value.trim()) return;
    
    const text = message.value.trim();
    
    if (text.startsWith('/')) {
        handleCommand(text);
    } else {
        await chatStore.addMessage({
            type: 'user',
            user: chatStore.nickname,
            text
        });
        SoundManager.playTypingSound();
    }
    
    message.value = '';
    scrollToBottom();
};

const handleCommand = (cmd) => {
    const parts = cmd.split(' ');
    const command = parts[0].toLowerCase();
    
    if (command === '/me' && parts.length > 1) {
        chatStore.addMessage({
            type: 'action',
            text: `* ${chatStore.nickname} ${parts.slice(1).join(' ')}`
        });
    } else if (command === '/topic' && parts.length > 1) {
        chatStore.updateTopic(parts.slice(1).join(' '));
    } else if (command === '/nick' && parts.length > 1) {
        chatStore.changeNickname(parts[1]);
    } else if (command === '/clear') {
        chatStore.clearHistory();
    } else if (command === '/help') {
        chatStore.addMessage({ 
            type: 'system', 
            text: 'Commands: /me <action>, /topic <text>, /nick <name>, /clear, /help',
            localOnly: true 
        });
    } else {
        chatStore.addMessage({ 
            type: 'system', 
            text: `*** Unknown command: ${command}`,
            localOnly: true 
        });
    }
    scrollToBottom();
};

// Auto-scroll when new messages arrive or history is cleared
watch(() => chatStore.messages, () => {
    scrollToBottom();
}, { deep: true });

onMounted(() => {
    if (chatStore.isConnected) {
        chatStore.startPolling();
        scrollToBottom();
        // Small delay ensures users manually cycling past the tab with arrows don't get trapped in the input
        setTimeout(focusInput, 500);
    }
});

onUnmounted(() => {
// Polling remains active globally for background sync
});

const formatTime = (isoString) => {
    if (!isoString) return '--:--';
    const date = new Date(isoString);
    return date.toLocaleTimeString('en-GB', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: false 
    });
};
</script>

<template>
  <div class="section-content animate-in">
    <h3>> IRC.OWNEDGE.NET</h3>

    <!-- IRC Interface -->
    <div v-if="chatStore.isConnected" class="irc-container">
      <div class="irc-main">
        <div class="irc-header">
          <div class="header-main">
            <span class="chan">#OWNEDGE</span>
            <span class="topic">"{{ chatStore.topic.text }}"</span>
            <span class="topic-meta">set by {{ chatStore.topic.author }} on {{ chatStore.topic.modified }}</span>
            <span v-if="!chatStore.isServerOnline" class="server-status">[OFFLINE]</span>
          </div>
        </div>
        <div class="irc-log" ref="logContainer">
          <div v-for="msg in chatStore.messages" :key="msg.id" :class="['msg', msg.type]">
            <span class="msg-time">{{ formatTime(msg.timestamp) }}</span>
            <template v-if="msg.type === 'system'">
              <span class="msg-content">{{ msg.text }}</span>
            </template>
            <template v-else-if="msg.type === 'action'">
              <span class="msg-content">{{ msg.text }}</span>
            </template>
            <template v-else>
              <span class="msg-user">&lt;{{ msg.user }}&gt;</span>
              <span class="msg-text">{{ msg.text }}</span>
            </template>
          </div>
        </div>
        <div class="irc-input-row">
        <div class="input-wrapper">
          <input 
            ref="messageInput"
            v-model="message" 
            type="text" 
            autocomplete="off"
            placeholder="type message or /command..."
            @keydown.enter="handleSend"
          />
        </div>
      </div>
      </div>
      <div class="irc-sidebar">
        <div class="sidebar-header">USERS [{{ chatStore.users.length + 1 }}]</div>
        <div class="user-list">
          <div class="user-item self">{{ chatStore.nickname }}</div>
          <div v-for="u in chatStore.users" :key="u" class="user-item">{{ u }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.section-content h3 {
    margin-top: 0;
    color: var(--color-accent);
    border-bottom: 1px solid rgba(64, 224, 208, 0.3);
    display: inline-block;
    padding-bottom: 5px;
    margin-bottom: 20px;
    font-size: 1.4rem;
    letter-spacing: 1px;
    align-self: flex-start;
}

.section-content {
    display: flex;
    flex-direction: column;
    height: 90%;
    padding: 0 !important;
}

.irc-container {
    display: grid;
    grid-template-columns: 1fr 200px; /* Slightly wider sidebar */
    flex: 1; /* Fill available height */
    background: #050505;
    border: 1px solid #333;
    font-family: 'Microgramma', sans-serif;
    letter-spacing: 0.5px;
    overflow: hidden; /* Prevent container from expanding */
}

.irc-main {
    display: flex;
    flex-direction: column;
    border-right: 1px solid #333;
    min-height: 0; /* CRITICAL: Allow flex item to shrink below content height */
    height: 100%; /* Force it to fill the irc-container grid cell */
}

.irc-header {
    background: #111;
    padding: 8px 14px;
    border-bottom: 1px solid #333;
    font-size: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.header-main {
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-meta {
    display: flex;
    align-items: center;
    gap: 15px;
    font-size: 0.75rem;
}

.irc-header .chan { color: var(--color-accent); font-weight: bold; }
.irc-header .topic { color: #fff; font-weight: bold; }
.irc-header .topic-meta { color: #666; font-family: 'Microgramma', sans-serif; letter-spacing: 0.5px; }
.irc-header .server-status { color: #ff0000; font-size: 0.7rem; font-weight: bold; background: rgba(255,0,0,0.1); padding: 0 5px; }

.irc-log {
    flex: 1;
    padding: 12px;
    overflow-y: auto;
    font-size: 0.9rem;
    scrollbar-width: thin;
    scrollbar-color: #333 transparent;
}

.msg { margin-bottom: 6px; line-height: 1.5; display: flex; gap: 8px; }
.msg-time { color: #555; font-size: 0.9rem; flex-shrink: 0; }
.msg.system { color: #00ff00; font-size: 0.9rem; opacity: 0.8; }
.msg.action { color: var(--color-accent); font-style: italic; }
.msg-user { color: #fff; font-weight: bold; flex-shrink: 0; }
.msg-text { color: rgba(255,255,255,0.9); }
.msg-content { white-space: pre-wrap; word-break: break-all; }

.irc-input-row {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    background: #0a0a0a;
    border-top: 1px solid #222;
    gap: 12px;
}

.input-wrapper {
    position: relative;
    flex: 1;
    display: flex;
    align-items: center;
}


.irc-input-row input {
    flex: 1;
    background: transparent;
    border: none;
    color: #fff;
    font-family: 'Microgramma', sans-serif;
    font-size: 1.05rem;
    outline: none;
    padding: 0 10px;
    letter-spacing: 0.5px;
}

.irc-sidebar {
    background: #0a0a0a;
    display: flex;
    flex-direction: column;
    min-height: 0; /* CRITICAL: Allow flex item to shrink below content height */
    height: 100%; /* Force it to fill the irc-container grid cell */
}

.sidebar-header {
    padding: 8px 14px;
    background: #111;
    border-bottom: 1px solid #333;
    font-size: 0.85rem;
    color: #666;
    letter-spacing: 1px;
}

.user-list {
    flex: 1;
    padding: 10px;
    font-size: 0.95rem;
    overflow-y: auto; /* Scrollable users if list is long */
    scrollbar-width: thin;
    scrollbar-color: #333 transparent;
}

.user-item { color: #ccc; margin-bottom: 4px; }
.user-item.self { color: var(--color-accent); }

.animate-in { animation: slideUp 0.3s ease-out; }
@keyframes slideUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.fade-leave-active { transition: opacity 0.5s; }
.fade-leave-to { opacity: 0; }
@media (max-width: 900px) {
    .irc-container {
        grid-template-columns: 1fr; /* Hide sidebar list */
    }
    .irc-sidebar {
        display: none !important;
    }
    .irc-header .topic {
        display: none !important; /* Hide topic for space */
    }
    .topic-meta {
        font-size: 0.6rem;
    }
    .chan {
        font-size: 0.8rem;
    }
    .msg-time {
        font-size: 0.75rem;
    }
    .msg-text, .msg-user {
        font-size: 0.85rem;
    }
    .irc-input-row {
        padding: 10px;
    }
    .irc-input-row input {
        font-size: 0.9rem;
    }
    .irc-log {
        scrollbar-width: none;
    }
    .irc-log::-webkit-scrollbar {
        display: none;
    }
}
</style>
