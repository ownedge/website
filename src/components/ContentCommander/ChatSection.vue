<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue';
import SoundManager from '../../sfx/SoundManager';
import { chatStore } from '../../store/chatStore';
import { keyboardStore } from '../../store/keyboardStore';

const message = ref('');
const messageInput = ref(null);
const logContainer = ref(null);
const isValidNickname = computed(() => chatStore.nickname.trim().length >= 3);

const focusInput = async () => {
    if (window.innerWidth <= 900) return; // Don't focus on mobile to avoid native keyboard

    // Retry focus a few times to account for rendering/animation.
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
            text: `* ${chatStore.chatNickname || chatStore.nickname} ${parts.slice(1).join(' ')}`
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

const openVirtualKeyboard = () => {
    if (window.innerWidth > 900) return;
    
    keyboardStore.open((key) => {
        if (key === 'BACKSPACE') {
            message.value = message.value.slice(0, -1);
        } else if (key === 'ENTER') {
           handleSend();
        } else {
             message.value += key;
        }
    });
};

const handleInputClick = () => {
    if (window.innerWidth <= 900) {
        openVirtualKeyboard();
    } else {
        messageInput.value?.focus();
    }
};

// Auto-scroll when new messages arrive or history is cleared
watch(() => chatStore.messages, () => {
    scrollToBottom();
}, { deep: true });

import mapPng from '../../assets/geo_map.png';

const mapCanvas = ref(null);
let mapPoints = [];
let animationId = null;

const initMap = () => {
    const img = new Image();
    img.src = mapPng;
    img.onload = () => {
        const offCanvas = document.createElement('canvas');
        const ctx = offCanvas.getContext('2d');
        // High density scan
        const width = 480; 
        const height = 100;
        offCanvas.width = width;
        offCanvas.height = height;
        
        ctx.drawImage(img, 0, 0, width, height);
        const imgData = ctx.getImageData(0, 0, width, height).data;
        
        mapPoints = [];
        // Scan every pixel for maximum density
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                const index = (y * width + x) * 4;
                const r = imgData[index];
                const g = imgData[index + 1];
                const b = imgData[index + 2];
                const a = imgData[index + 3];
                
                if (a > 100 && (r + g + b) > 150) {
                    mapPoints.push({
                        x: x / width,
                        y: y / height,
                        waveOffset: (x / width) * 10 
                    });
                }
            }
        }
        startMapAnimation();
    };
    img.onerror = (e) => {
        console.error("Failed to load map image", e);
    };
};

const startMapAnimation = () => {
    if (!mapCanvas.value) return;
    const canvas = mapCanvas.value;
    const ctx = canvas.getContext('2d');
    
    // Get accent color
    const computedStyle = getComputedStyle(document.body);
    const accentColor = computedStyle.getPropertyValue('--color-accent').trim() || '#40e0d0'; // Fallback
    
    const draw = (time) => {
        if (!canvas.isConnected) return; // Stop if unmounted
        
        const rect = canvas.parentElement.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const physicalWidth = rect.width * dpr;
        const physicalHeight = rect.height * dpr;

        // Resize check handling DPR
        if (canvas.width !== physicalWidth || canvas.height !== physicalHeight) {
            canvas.width = physicalWidth;
            canvas.height = physicalHeight;
        }
        
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = accentColor;
        
        // Preserve 2:1 Aspect Ratio
        const targetRatio = 2.0; 
        const canvasRatio = canvas.width / canvas.height;
        
        let mapW, mapH;
        
        if (canvasRatio > targetRatio) {
            // Canvas is wider than map -> constrain by height
            mapH = canvas.height;
            mapW = mapH * targetRatio * 0.7;
        } else {
            // Canvas is narrower -> constrain by width
            mapW = canvas.width;
            mapH = mapW / targetRatio;
        }
        
        const offsetX = (canvas.width - mapW) / 2;
        const offsetY = (canvas.height - mapH) / 2;
        
        mapPoints.forEach(p => {
            const x = (p.x * mapW) + offsetX;
            const y = (p.y * mapH) + offsetY;
            
            // Uniform opacity (no gradient/pulse)
            ctx.globalAlpha = 0.25;
            
            ctx.beginPath();
            // High res scale
            const size = Math.max(0.5 * dpr, mapW / 1400);
            ctx.arc(x, y, size, 0, Math.PI * 2);
            ctx.fill();
        });
        
        // Draw Connected Users
        const usersToPlot = [];
        
        // Me
        if (chatStore.userLat && chatStore.userLon) {
             usersToPlot.push({ lat: chatStore.userLat, lon: chatStore.userLon, isMe: true });
        }

        // Others
        if (chatStore.users) {
            chatStore.users.forEach(u => {
                if (u.lat && u.lon) {
                    // Use real location from server
                    usersToPlot.push({ lat: u.lat, lon: u.lon, isMe: false });
                } else {
                    // Fallback: Pseudo-random locations based on nick hash
                    let hash = 0;
                    const nick = u.nickname || 'Guest';
                    for (let i = 0; i < nick.length; i++) hash = nick.charCodeAt(i) + ((hash << 5) - hash);
                    // Lat: -60 to 75. Lon: -180 to 180.
                    const lat = (Math.abs(hash) % 135) - 60;
                    const lon = (Math.abs(hash * 31) % 360) - 180;
                    usersToPlot.push({ lat, lon, isMe: false });
                }
            });
        }

        usersToPlot.forEach(u => {
             // Mercator Projection: Matches standard web maps better
             // Clamp lat to -85 to 85 to avoid infinity
             const safeLat = Math.max(-85, Math.min(85, u.lat));
             const latRad = safeLat * Math.PI / 180;
             const mercN = Math.log(Math.tan((Math.PI / 4) + (latRad / 2)));
             // Map mercN (-PI to PI) to 0..1
             const uY = (0.5 - (mercN / (2 * Math.PI))) * mapH + offsetY;
             
             const uX = ((u.lon + 180) / 360) * mapW + offsetX;
             
             // Draw Dot
             ctx.fillStyle = '#fff'; // Bright white center
             ctx.globalAlpha = 1.0;
             ctx.beginPath();
             const dotSize = Math.max(1.5 * dpr, mapW / 200); // Prominent dot
             ctx.arc(uX, uY, dotSize * 0.6, 0, Math.PI * 2);
             ctx.fill();
             
             // Glow
             ctx.fillStyle = accentColor;
             ctx.globalAlpha = 0.5;
             ctx.beginPath();
             ctx.arc(uX, uY, dotSize, 0, Math.PI * 2);
             ctx.fill();

             // Draw PING Ring
             ctx.strokeStyle = accentColor;
             ctx.lineWidth = 1 * dpr;
             // Varies slightly per user to desync
             const offset = u.lon * 10; 
             const ringPulse = ((time + offset) % 3000) / 3000; 
             const maxRing = dotSize * 8;
             
             ctx.globalAlpha = Math.max(0, 1 - ringPulse); 
             ctx.beginPath();
             ctx.arc(uX, uY, dotSize + (maxRing * ringPulse), 0, Math.PI * 2);
             ctx.stroke(); 
        });
        
        animationId = requestAnimationFrame(draw);
    };
    animationId = requestAnimationFrame(draw);
};

onMounted(() => {
    if (chatStore.isConnected) {
        chatStore.startPolling();
        scrollToBottom();
        // Auto-open keyboard on mobile
        if (window.innerWidth <= 900) {
            setTimeout(openVirtualKeyboard, 500);
        } else {
            setTimeout(focusInput, 500);
        }
        
        // Initialize Map
        initMap();
    }
});

onUnmounted(() => {
    if (animationId) cancelAnimationFrame(animationId);
    // Ensure keyboard closes if on mobile
    if (window.innerWidth <= 900) {
        keyboardStore.close();
    }
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
const showMobileUsers = ref(false);

const toggleMobileUsers = () => {
    showMobileUsers.value = !showMobileUsers.value;
    SoundManager.playTypingSound();
};

const displayedUsers = computed(() => {
    try {
        const selfNick = chatStore.chatNickname || chatStore.nickname || 'Guest';
        const usersList = Array.isArray(chatStore.users) ? chatStore.users : [];
        const others = usersList
            .filter(u => u && u.nickname)
            .map(u => u.nickname);
            
        const allUsers = [selfNick, ...others];
        
        // Safety check
        if (allUsers.length === 0) return 'USERS: (0)';
        
        return `USERS: ${allUsers.join(', ')}`;
    } catch (e) {
        console.error("Error formatting users:", e);
        return "USERS";
    }
});
const isVirtualMode = computed(() => keyboardStore.isVisible.value && window.innerWidth <= 900);
</script>

<template>
  <div class="section-content animate-in">
    <h3>> IRC.OWNEDGE.NET</h3>

    <!-- IRC Interface -->
    <div v-if="chatStore.isConnected" class="irc-container">
      <div class="irc-main">
        <canvas ref="mapCanvas" class="map-bg"></canvas>
        <div class="irc-header">
          <div class="header-main">
            <span class="chan">#OWNEDGE</span>
            <span class="topic">"{{ chatStore.topic.text }}"</span>
            <span class="topic-meta">set by {{ chatStore.topic.author }} on {{ chatStore.topic.modified }}</span>
            <span v-if="!chatStore.isServerOnline" class="server-status">[OFFLINE]</span>
          </div>
          <div class="mobile-users-toggle" @click="toggleMobileUsers">
            {{ displayedUsers }}
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
        <div class="input-wrapper" @click="handleInputClick">
          <div v-if="isVirtualMode" class="virtual-input-display">
            {{ message }}<span class="cursor-block">_</span>
          </div>
          <input 
            v-else
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
      <div class="irc-sidebar" :class="{ 'active': showMobileUsers }">
        <div class="sidebar-header">
            USERS [{{ chatStore.users.length + 1 }}]
            <span class="close-sidebar" @click="toggleMobileUsers">x</span>
        </div>
        <div class="user-list">
          <div class="user-item self">{{ chatStore.chatNickname || chatStore.nickname }}</div>
          <div v-for="u in chatStore.users" :key="u.nickname" class="user-item">{{ u.nickname }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ... existing styles ... */
.mobile-users-toggle {
    display: none;
    font-size: 0.75rem;
    color: var(--color-accent);
    border: 1px solid var(--color-accent);
    padding: 2px 6px;
    cursor: pointer;
    background: rgba(64, 224, 208, 0.1);
    
    /* Truncation */
    /* max-width: 150px; -- REMOVED */
    width: 100%;
    margin-top: 4px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

.close-sidebar {
    display: none;
    cursor: pointer;
    float: right;
    color: var(--color-accent);
}

/* ... existing styles ... */

@media (max-width: 900px) {
    /* ... existing ... */
    
    .irc-input-row input.virtual-mode {
        pointer-events: none; /* Disable native interaction */
        caret-color: transparent;
        border-bottom: 1px solid var(--color-accent); /* Make it look like a line */
    }
}

/* ... existing styles ... */

@media (max-width: 900px) {
    .irc-container {
        grid-template-columns: 1fr; /* Hide sidebar list */
        position: relative;
    }
    .irc-sidebar {
        display: none;
        position: absolute;
        top: 0;
        right: 0;
        width: 200px;
        height: 100%;
        background: #0a0a0a;
        border-left: 1px solid #333;
        z-index: 100;
        padding-bottom: 20px;
        box-shadow: -5px 0 20px rgba(0,0,0,0.5);
    }
    .irc-sidebar.active {
        display: flex !important;
    }
    
    .mobile-users-toggle {
        display: block;
    }
    
    .close-sidebar {
        display: inline-block;
    }

    /* ... other existing overrides ... */
    .irc-header .topic {
        display: none !important; /* Hide topic for space */
    }
    /* ... */
}
</style>

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
    position: relative;
    overflow: hidden;
}

.map-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
    pointer-events: none;
}

.irc-header {
    background: #111;
    padding: 8px 14px;
    border-bottom: 1px solid #333;
    font-size: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 4px;
    position: relative;
    z-index: 1;
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
    position: relative;
    z-index: 1;
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
    position: relative;
    z-index: 1;
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
    
    /* Hide caret on mobile for "terminal" feel with virtual keyboard */
    input[readonly] {
        caret-color: transparent;
    }
    
    .virtual-input-display {
        width: 100%;
        background: transparent;
        color: #fff;
        font-family: 'Microgramma', sans-serif;
        font-size: 0.9rem;
        padding: 0 10px;
        letter-spacing: 0.5px;
        min-height: 20px;
        pointer-events: none; /* Let wrapper click open keyboard */
        border-bottom: 2px solid var(--color-accent);
    }
    
    .cursor-block {
        display: inline-block;
        width: 10px;
        height: 1.2em;
        background: var(--color-accent); /* transparent block for now since user wants chars one by one */
        margin-left: 2px;
        animation: blink 1s step-end infinite;
        vertical-align: bottom;
    }
    
    @keyframes blink { 50% { opacity: 0; } }
}
</style>
