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

const isNicknameModalOpen = ref(false);
const newNickname = ref('');

const openNicknameModal = () => {
    newNickname.value = chatStore.chatNickname || chatStore.nickname || '';
    isNicknameModalOpen.value = true;
    SoundManager.playTypingSound();
    // Focus next tick
    nextTick(() => {
        const input = document.getElementById('nickname-input');
        if (input) input.focus();
    });
};

const submitNickname = () => {
    if (newNickname.value.trim().length >= 3) {
        chatStore.changeNickname(newNickname.value.trim());
        isNicknameModalOpen.value = false;
        SoundManager.playTypingSound();
    }
};

const handleFKey = (key) => {
    if (key === 'F3') {
        openNicknameModal();
    }
};

defineExpose({ handleFKey });

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
                    const normY = y / height;
                    const MAP_CROP = 0.78;
                    
                    // Filter South Pole
                    if (normY > MAP_CROP) continue;
                    
                    mapPoints.push({
                        x: x / width,
                        y: normY / MAP_CROP,
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
    const ctx = canvas.getContext('2d', { alpha: false }); // Optimize for no alpha if opaque bg possible (but we overlap)
    
    // Performance Optimization: Offscreen canvas for static map dots
    let bgCanvas = document.createElement('canvas');
    let bgCtx = bgCanvas.getContext('2d');
    let lastW = 0, lastH = 0;
    
    // Animation State
    let animStartTime = performance.now();
    
    // Get accent color
    const computedStyle = getComputedStyle(document.body);
    const accentColor = computedStyle.getPropertyValue('--color-accent').trim() || '#40e0d0'; // Fallback
    
    const draw = (time) => {
        if (!canvas.isConnected) return; // Stop if unmounted
        
        const rect = canvas.parentElement.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const physicalWidth = Math.floor(rect.width * dpr);
        const physicalHeight = Math.floor(rect.height * dpr);

        // Resize check handling DPR
        const sizeChanged = canvas.width !== physicalWidth || canvas.height !== physicalHeight;
        if (sizeChanged) {
            canvas.width = physicalWidth;
            canvas.height = physicalHeight;
        }

        // ...
        
        // Define Crop Factor (Cut off bottom 20% - Antarctica & Southern Ocean)
        const MAP_CROP = 0.78;

        // --- Calculate Map Geometry (Normalized) ---
        // We reuse this logic for both static cache and dynamic points
        const targetRatio = 2.0; 
        const canvasRatio = (physicalWidth / physicalHeight) || 1; // Avoid divide by zero
        let mapW, mapH;
        
        if (canvasRatio > targetRatio) {
            mapH = physicalHeight;
            mapW = mapH * targetRatio * 0.82;
        } else {
            mapW = physicalWidth;
            mapH = mapW / targetRatio;
        }
        
        const offsetX = (physicalWidth - mapW) / 2;
        const offsetY = (physicalHeight - mapH) / 2;


        // --- 1. Draw/Update Static Background Cache (Only on Resize) ---
        if (sizeChanged || lastW !== physicalWidth || lastH !== physicalHeight) {
            bgCanvas.width = physicalWidth;
            bgCanvas.height = physicalHeight;
            lastW = physicalWidth;
            lastH = physicalHeight;
            
            // Clear Background
            bgCtx.clearRect(0, 0, bgCanvas.width, bgCanvas.height);
            bgCtx.fillStyle = accentColor;
            
            // Batch Draw Static Dots
            // Reduce opacity on mobile to avoid clutter
            bgCtx.globalAlpha = window.innerWidth <= 900 ? 0.1 : 0.3;
            
            mapPoints.forEach(p => {
                const x = (p.x * mapW) + offsetX;
                const y = (p.y * mapH) + offsetY;
                
                bgCtx.beginPath();
                const size = Math.max(0.6 * dpr, mapW / 1900);
                bgCtx.arc(x, y, size, 0, Math.PI * 2);
                bgCtx.fill();
            });
        }
        
        // --- 2. Main Render Loop (Per Frame) ---
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // --- INTRO ANIMATION LOGIC ---
        const duration = 4000;
        const elapsed = time - animStartTime;
        let progress = Math.min(elapsed / duration, 1);
        
        // Easing: Exponential Ease Out
        // 1 - Math.pow(2, -10 * progress) for standard ease out
        const ease = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
        
        // Calculate Camera
        // Start: Scale 4, Center = User Pos
        // End: Scale 1, Center = Screen Center
        const startScale = 4.0;
        const endScale = 1.0;
        const currentScale = startScale + (endScale - startScale) * ease;
        
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        
        // Identify User Position for Focus
        let focusX = centerX;
        let focusY = centerY;
        
        if (chatStore.userLat && chatStore.userLon) {
             const safeLat = Math.max(-85, Math.min(85, chatStore.userLat));
             const latRad = safeLat * Math.PI / 180;
             const mercN = Math.log(Math.tan((Math.PI / 4) + (latRad / 2)));
             let normY = (0.5 - (mercN / (2 * Math.PI)));
             
             // Apply Crop Stretch
             normY = normY / MAP_CROP;
             
             focusY = normY * mapH + offsetY;
             focusX = ((chatStore.userLon + 180) / 360) * mapW + offsetX;
        }

        // Interpolate Focus Point from User (Start) to Center (End)
        // If we want a pure zoom out from the point, we keep the focus point on the User until the end?
        // User request: "zoom out the world map starting centered on user's location"
        // This usually implies the camera moves from User -> Center of Map OR just zooms out while focused on User?
        // "Zoom out the world map... starting centered on user" usually implies tracking back to the full view.
        // Full view center is centerX, centerY.
        // Start view center is focusX, focusY.
        
        const currentFocusX = focusX + (centerX - focusX) * ease;
        const currentFocusY = focusY + (centerY - focusY) * ease;
        
        ctx.save();
        ctx.translate(centerX, centerY);
        ctx.scale(currentScale, currentScale);
        ctx.translate(-currentFocusX, -currentFocusY);

        
        // A. Blit the cached background
        ctx.globalAlpha = 1.0;
        ctx.drawImage(bgCanvas, 0, 0);
        
        
        // C. Draw Connected Users
        const usersToPlot = [];
        
        // Me
        if (chatStore.userLat && chatStore.userLon) {
             usersToPlot.push({ lat: chatStore.userLat, lon: chatStore.userLon, isMe: true });
        }

        // Others
        if (chatStore.users) {
            chatStore.users.forEach(u => {
                if (u.lat && u.lon) {
                    usersToPlot.push({ lat: u.lat, lon: u.lon, isMe: false });
                } else {
                    let hash = 0;
                    const nick = u.nickname || 'Guest';
                    for (let i = 0; i < nick.length; i++) hash = nick.charCodeAt(i) + ((hash << 5) - hash);
                    const lat = (Math.abs(hash) % 135) - 60;
                    const lon = (Math.abs(hash * 31) % 360) - 180;
                    usersToPlot.push({ lat, lon, isMe: false });
                }
            });
        }

        usersToPlot.forEach(u => {
             const safeLat = Math.max(-85, Math.min(85, u.lat));
             const latRad = safeLat * Math.PI / 180;
             const mercN = Math.log(Math.tan((Math.PI / 4) + (latRad / 2)));
             let normY = (0.5 - (mercN / (2 * Math.PI)));
             
             // Apply Crop Stretch to Users
             normY = normY / MAP_CROP;
             
             const uY = normY * mapH + offsetY;
             const uX = ((u.lon + 180) / 360) * mapW + offsetX;
             
             // Draw Dot
             ctx.fillStyle = '#fff'; // Bright white center
             ctx.globalAlpha = 0.9;
             ctx.beginPath();
             const dotSize = Math.max(1.5 * dpr, mapW / 200); // Prominent dot
             ctx.arc(uX, uY, dotSize * 0.6, 0, Math.PI * 2);
             ctx.fill();
             
             // Glow
             ctx.fillStyle = accentColor;
             ctx.globalAlpha = 0.45;
             ctx.beginPath();
             ctx.arc(uX, uY, dotSize, 0, Math.PI * 2);
             ctx.fill();

             // Draw PING Ring
             ctx.strokeStyle = accentColor;
             ctx.lineWidth = 1 * dpr;
             const offset = u.lon * 10; 
             const ringPulse = ((time + offset) % 3000) / 3000; 
             const maxRing = dotSize * 7;
             
             ctx.globalAlpha = Math.max(0, 1 - ringPulse); 
             ctx.beginPath();
             // Adjust ring size scaling to look good even when zoomed in
             // If zoomed in (low 'ease'), we might want rings to be consistent visual size or logical size?
             // Logical size works best for "world" feel.
             ctx.arc(uX, uY, dotSize + (maxRing * ringPulse), 0, Math.PI * 2);
             ctx.stroke(); 
        });
        
        ctx.restore();
        
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
            <span v-if="!message" class="cursor-block"> </span>
            <span v-if="!message" class="virtual-placeholder">type message...</span>
            {{ message }}<span v-if="message" class="cursor-block"> </span>
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
    <!-- Nickname Modal -->
    <Transition name="fade">
        <div v-if="isNicknameModalOpen" class="modal-overlay" @click.self="isNicknameModalOpen = false">
            <div class="modal-content">
                <div class="popup-header">
                    <span>CHANGE NICKNAME</span>
                    <div class="esc-label" @click="isNicknameModalOpen = false">ESC</div>
                </div>
                <div class="form-body">
                    <div class="input-wrapper focus-locked active">
                         <input 
                            id="nickname-input"
                            v-model="newNickname" 
                            type="text" 
                            maxlength="20" 
                            placeholder="Enter new nickname..."
                            @keydown.enter="submitNickname"
                            style="background: #111; padding: 10px; width: 100%; border: 1px solid #333; color: #fff; font-family: 'Microgramma', monospace;"
                        />
                    </div>
                    <div class="modal-actions" style="margin-top: 20px; text-align: center;">
                        <button class="sign-btn custom-btn" @click="submitNickname">[ SAVE ]</button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
  </div>
</template>

<style scoped>
.section-content {
    display: flex;
    flex-direction: column;
    height: 90%;
    padding: 0 !important;
}

.section-content h3 {
    margin-top: 0;
    color: var(--color-accent);
    border-bottom: 1px solid rgba(64, 224, 208, 0.3);
    display: inline-block;
    padding-left: 10px;
    padding-bottom: 5px;
    margin-bottom: 20px;
    font-size: 1.2rem;
    letter-spacing: 1px;
    align-self: flex-start;
}

.mobile-users-toggle {
    display: none;
    font-size: 0.75rem;
    color: var(--color-accent);
    border: 1px solid var(--color-accent);
    padding: 2px 6px;
    cursor: pointer;
    background: rgba(64, 224, 208, 0.1);
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

.irc-container {
    display: grid;
    grid-template-columns: 1fr 200px;
    flex: 1;
    background: #050505;
    border: 1px solid #333;
    font-family: 'Microgramma', sans-serif;
    letter-spacing: 0.5px;
    overflow: hidden;
}

.irc-main {
    display: flex;
    flex-direction: column;
    border-right: 1px solid #333;
    min-height: 0;
    height: 100%;
    position: relative;
    overflow: hidden;
}

.map-bg {
    position: absolute;
    top: 5%;
    left: 0;
    width: 100%;
    height: 90%;
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
    z-index: 2;
    text-shadow: 0 1px 3px #000, 0 0 5px #000;
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
    min-height: 0;
    height: 100%;
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
    overflow-y: auto;
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
    .section-content h3 {
        padding-top: 12px;
    }

    .irc-container {
        grid-template-columns: 1fr;
        border-left: none;
        border-right: none;
        border-bottom: none;
        position: relative;
    }

    /* Mobile Drawer Sidebar */
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

    .irc-header .topic {
        display: none !important;
    }

    .topic-meta { font-size: 0.6rem; }
    .chan { font-size: 0.8rem; }
    .msg-time { font-size: 0.75rem; }
    .msg-text, .msg-user { font-size: 0.85rem; }

    .irc-input-row { padding: 10px; }
    .irc-input-row input { font-size: 0.9rem; }

    .irc-log {
        scrollbar-width: none;
    }
    .irc-log::-webkit-scrollbar {
        display: none;
    }

    .irc-input-row input.virtual-mode {
        pointer-events: none;
        caret-color: transparent;
        border-bottom: 1px solid var(--color-accent);
    }

    input[readonly] {
        caret-color: transparent;
    }

    .virtual-input-display {
        width: 100%;
        background: transparent;
        color: #ffffff;
        font-family: 'Microgramma', sans-serif;
        font-size: 0.9rem;
        padding: 0 10px;
        letter-spacing: 0.5px;
        min-height: 20px;
        pointer-events: none;
        border-bottom: 1px solid #4e4e4e;
    }

    .cursor-block {
        display: inline-block;
        width: 1px;
        height: 1.4em;
        background: #f1f1f1;
        margin-left: 2px;
        animation: blink 1s step-end infinite;
        vertical-align: bottom;
    }
    
    .virtual-placeholder {
        color: #6f6f6f;
    }

    @keyframes blink { 50% { opacity: 0; } }
}

/* Modal Styling */
.modal-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    backdrop-filter: blur(2px);
}

.modal-content {
    background: #000;
    border: 2px solid var(--color-accent);
    box-shadow: 0 0 20px rgba(64, 224, 208, 0.2);
    width: 90%;
    max-width: 400px;
    position: relative;
    max-height: 90vh;
}

.popup-header {
    background: var(--color-accent);
    color: #000;
    padding: 8px 15px;
    font-weight: bold;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.esc-label {
    background: #000;
    color: var(--color-accent);
    font-size: 0.7rem;
    padding: 2px 6px;
    cursor: pointer;
}

.form-body {
    padding: 20px;
}

.custom-btn {
    border: 1px solid var(--color-accent);
    color: var(--color-accent);
    padding: 8px 16px;
    background: transparent;
    cursor: pointer;
    font-family: 'Microgramma', monospace;
}
.custom-btn:hover {
    background: rgba(64, 224, 208, 0.1);
}
</style>
