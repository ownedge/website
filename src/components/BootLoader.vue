<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue';
import SoundManager from '../sfx/SoundManager';
import { chatStore } from '../store/chatStore';

const videoRef = ref(null);
const canvasRef = ref(null);
const nicknameInputRef = ref(null);

const props = defineProps({
  isBooted: { type: Boolean, default: false }
});
const emit = defineEmits(['start', 'progress', 'ready', 'connecting', 'status-update', 'skip']);

const bootStage = ref('bios'); // bios, login, connecting, ready
const bootMessages = ref([]);
const isConnecting = ref(false);
let animationFrameId = null;

const VISUALIZATION_CONFIG = {
    minFreqIndex: 0,
    maxFreqIndex: 15,
    threshold: 150,
    dotSize: 8.5,
    gap: 5,
    horizontalPadding: 10
};

const isValidNickname = computed(() => {
    const n = chatStore.nickname.trim();
    return n.length >= 3;
});

const parseMessage = (text) => {
    // Regex to split by chunks of special characters: > or █ or ✔
    // We keep the delimiters in the result
    const parts = text.split(/([>█+✔]+)/g);
    return parts.map(part => ({
        text: part,
        isHighlight: /^[>█+✔]+$/.test(part)
    })).filter(p => p.text !== '');
};

const addMessage = (text, delay = 0) => {
    return new Promise(resolve => {
        setTimeout(() => {
            bootMessages.value.push(parseMessage(text));
            SoundManager.playTypingSound();
            resolve();
        }, delay);
    });
};

const runBiosSequence = async () => {
    emit('progress', 0);
    await addMessage('> BIOS VERSION 4.2.0 (C) 1994 OWNEDGE CORP', 500);
    emit('progress', 10);
    const memLineIdx = bootMessages.value.length;
    bootMessages.value.push(''); 
    const targetMem = 65536;
    const memStep = 2048;
    for (let m = 0; m <= targetMem; m += memStep) {
        bootMessages.value[memLineIdx] = parseMessage(`> MEMORY TEST: ${m} KB`);
        await new Promise(r => setTimeout(r, 20));
    }
    bootMessages.value[memLineIdx] = parseMessage(`> MEMORY TEST: ${targetMem} KB ✔`);
    SoundManager.playTypingSound();
    emit('progress', 20);
    await addMessage('> DETECTING PRIMARY MASTER... OWNEDGE-CORE-V1', 400);
    emit('progress', 30);
    await addMessage('> DRIVE 0: MOUNTING CORE_OS... ✔', 200);
    
    // Animate Kernel Loading Bar
    const barLineIdx = bootMessages.value.length;
    bootMessages.value.push(''); // Placeholder for animated bar
    for (let p = 0; p <= 100; p += 5) {
        const bars = '█'.repeat(Math.floor(p / 10));
        const spaces = ' '.repeat(10 - Math.floor(p / 10));
        bootMessages.value[barLineIdx] = parseMessage(`> LOADING KERNEL [${bars}${spaces}] ${p}%`);
        emit('progress', 30 + (p * 0.4)); // Map 30-70% to kernel load
        await new Promise(r => setTimeout(r, 40));
    }
    SoundManager.playTypingSound();
    
    emit('progress', 80);
    await addMessage('> INITIALIZING NETWORK STACK...', 500);
    emit('progress', 90);
    await addMessage('> IP ADDRESS: 127.0.0.1 (LOCALHOST)', 100);
    
    emit('progress', 100);
    emit('ready'); // VFD is now ready for interaction
    
    bootStage.value = 'login';
    await nextTick();
    nicknameInputRef.value?.focus();
};

const handleConnect = async () => {
    if (!isValidNickname.value || isConnecting.value) return;
    
    // Generate guest name if empty
    if (chatStore.nickname.trim() === '') {
        const rand = Math.floor(1000 + Math.random() * 9000);
        chatStore.nickname = `guest-${rand}`;
    }

    isConnecting.value = true;
    bootStage.value = 'connecting';
    emit('connecting');
    emit('status-update', 'dialing...');
    
    if (!SoundManager.initialized) SoundManager.init();
    
    await addMessage('> ESTABLISHING HANDSHAKE WITH REMOTE NODE...', 100);
    
    const soundPromise = SoundManager.playDialUpSound();
    
    // Status sequencing for VFD
    setTimeout(() => { 
        if (bootStage.value === 'connecting') emit('status-update', 'connecting...'); 
    }, 2850);
    
    setTimeout(() => { 
        if (bootStage.value === 'connecting') emit('status-update', 'LINK OK!'); 
    }, 6300);

    await nextTick();
    startVisualization();
    
    await soundPromise;
    
    bootStage.value = 'ready'; // Dismiss popup immediately after sound
    stopVisualization();
    await addMessage('> CONNECTED. SESSION ESTABLISHED. ✔', 200);
    
    // New Lines: Driver Loading
    await addMessage('> INITIALIZING MODULE LOADER...', 100);
    const driverLineIdx = bootMessages.value.length;
    bootMessages.value.push(parseMessage('LOADING DRIVERS [          ] 0%'));
    for (let p = 0; p <= 100; p += 10) {
        const bars = '█'.repeat(Math.floor(p / 10));
        const spaces = ' '.repeat(10 - Math.floor(p / 10));
        bootMessages.value[driverLineIdx] = parseMessage(`> LOADING DRIVERS [${bars}${spaces}] ${p}%`);
        await new Promise(r => setTimeout(r, 60));
    }
    await addMessage('> VIRTUAL FILE SYSTEM MOUNTED ✔', 100);
    await addMessage('> SYNCING WITH REMOTE CLUSTER...', 200);
    await addMessage('> SECURITY HANDSHAKE: ✔ VERIFIED', 200);
    
    await addMessage('> PREPARING WORKSPACE...', 300);
    
    // Log them into chatStore
    chatStore.isConnected = true;
    chatStore.showPopup = false;
    await chatStore.init();

    setTimeout(() => {
        emit('start');
    }, 800);
};

const handleSkip = () => {
    emit('skip');
};

const startVisualization = () => {
    if (!canvasRef.value) return;
    const canvas = canvasRef.value;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const draw = () => {
        const data = SoundManager.getDialUpAudioData();
        if (data) {
            // Trail Effect: Fade out previous frames instead of clearing instantly
            ctx.fillStyle = 'rgba(0, 0, 0, 0.05)'; // 0.2 = Long trails, 0.5 = Short trails
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            const { minFreqIndex, maxFreqIndex, threshold, dotSize, gap, horizontalPadding } = VISUALIZATION_CONFIG;
            
            // Find the single dominant frequency (Peak)
            let maxVal = -1;
            let maxIdx = -1;
            
            // Search only within our configured range
            // We map the visual X range to these indices
            const range = maxFreqIndex - minFreqIndex;
            const pointsToShow = Math.floor((canvas.width - (horizontalPadding * 2)) / (dotSize + gap));
            const step = range / pointsToShow; // This relates visual index 'i' to data index
            
            // Find and Draw Dominant Peak (Red Square) - Existing Logic
            let bestI = -1;

            for (let i = 0; i < pointsToShow; i++) {
                const dataIndex = Math.floor(minFreqIndex + (i * step));
                if (dataIndex < data.length) {
                    const val = data[dataIndex];
                    if (val > maxVal) {
                        maxVal = val;
                        maxIdx = dataIndex;
                        bestI = i;
                    }
                }
            }
            
            ctx.fillStyle = '#ff0000';

            // Draw ONLY the dominant peak
            if (maxVal > threshold && bestI !== -1) {
                const x = horizontalPadding + (bestI * (dotSize + gap));
                
                // Map Amplitude (0-255) to Y Position
                // 255 = Top (0), 0 = Bottom (height)
                // Invert logic: val/255 * height = height from bottom
                // Y = height - heightFromBottom
                const heightFromBottom = (maxVal / 255) * canvas.height;
                const y = canvas.height - heightFromBottom - (dotSize / 2);
                
                ctx.fillRect(x - dotSize/2, y, dotSize, dotSize);
            }
        }
        animationFrameId = requestAnimationFrame(draw);
    };
    draw();
};

const stopVisualization = () => {
    if (animationFrameId) cancelAnimationFrame(animationFrameId);
};

onMounted(() => {
  if (videoRef.value) videoRef.value.playbackRate = 0.8;
  
  // Auto-Nickname handling
  const savedNick = localStorage.getItem('chat_nickname');
  if (savedNick) {
      chatStore.nickname = savedNick;
  }

  if (!props.isBooted) {
    runBiosSequence();
  }
});

watch(() => props.isBooted, (val) => {
    if (val && videoRef.value) {
        setTimeout(() => {
            if (videoRef.value) videoRef.value.pause();
        }, 2500);
    }
});

onUnmounted(() => {
  stopVisualization();
});
</script>

<template>
  <div class="boot-loader">
    <video ref="videoRef" class="boot-video" autoplay muted loop playsinline>
        <source src="/ownedge.mp4" type="video/mp4">
    </video>
    
    <template v-if="!isBooted">
      <div class="scanlines-overlay"></div>
      
      <div class="terminal-overlay">
        <div class="terminal-content">
          <div v-for="(msg, i) in bootMessages" :key="i" class="boot-line">
            <span 
              v-for="(segment, si) in msg" 
              :key="si" 
              :class="{ 'highlight': segment.isHighlight }"
            >{{ segment.text }}</span>
          </div>
          
          <!-- Dial-Up Popup -->
          <transition name="fade">
            <div v-if="bootStage === 'login' || bootStage === 'connecting'" class="popup-overlay">
              <div class="popup-header">
                  <span>REMOTE NODE LINK</span>
                  <div class="esc-label" @click="handleSkip">ESC</div>
              </div>
              <div class="popup-body">
                <template v-if="bootStage === 'login'">
                    <p>PLEASE IDENTIFY YOUR TERMINAL NODE TO INITIALIZE SYNC.</p>
                    <div class="input-group">
                        <span class="prompt">NICKNAME:</span>
                        <div class="input-wrapper">
                            <input 
                                ref="nicknameInputRef"
                                v-model="chatStore.nickname" 
                                type="text" 
                                maxlength="12"
                                @keyup.enter="handleConnect"
                            />
                        </div>
                    </div>
                    <button 
                        class="connect-btn" 
                        :disabled="!isValidNickname"
                        @click="handleConnect"
                    >
                        [ CONNECT ]
                    </button>
                </template>

                <template v-else-if="bootStage === 'connecting'">
                    <div class="connection-status">
                      <canvas ref="canvasRef" width="300" height="60" class="viz-canvas"></canvas>
                      <div class="dialing-text">ESTABLISHING HANDSHAKE...</div>
                    </div>
                </template>
              </div>
            </div>
          </transition>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.boot-loader {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 1; /* Lowest child level for background */
  overflow: hidden;
  /* Disable pointer events after boot to avoid blocking content */
  pointer-events: v-bind("isBooted ? 'none' : 'auto'");
}

.boot-video {
    position: relative;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 101%;
    height: 101%;
    object-fit: cover;
    opacity: v-bind("isBooted ? 0 : 0.26"); 
    mix-blend-mode: color-burn;
    filter: grayscale(0.6) contrast(2.3) v-bind("isBooted ? 'brightness(0.5)' : 'brightness(1)'");
    transition: opacity 2s ease-in-out, filter 2s ease-in-out;
}

.terminal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    padding: 60px;
    display: flex;
    justify-content: flex-start;
    align-items: flex-start;
    color: var(--color-accent);
    font-family: 'Microgramma', monospace;
    z-index: 20;
    pointer-events: auto;
}

.terminal-content {
    width: 100%;
    max-width: 800px;
}

.boot-line {
    font-size: 1.1rem;
    line-height: 1.4;
    margin-bottom: 5px;
    color: rgba(255, 255, 255, 0.45); /* Greyed out by default */
    text-shadow: none;
}

.boot-line .highlight {
    color: var(--color-accent);
    text-shadow: 0 0 5px var(--color-accent);
}

/* Popup Styles */
.popup-overlay {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 350px;
    background: #000;
    opacity: 0.75;
    border: 1px solid var(--color-accent);
    box-shadow: 0 0 30px rgba(64, 224, 208, 0.1);
    z-index: 1000;
}

.popup-header {
    background: var(--color-accent);
    color: #000;
    padding: 4px 10px;
    font-weight: bold;
    font-size: 0.8rem;
    letter-spacing: 1px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.esc-label {
    font-size: 0.7rem;
    color: #000;
    cursor: pointer;
    font-family: 'JetBrains Mono', monospace;
    letter-spacing: 1px;
    padding: 0 5px;
    background: rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.2);
    transition: all 0.2s ease;
}

.esc-label:hover {
    background: #000;
    color: var(--color-accent);
}

.popup-body {
    padding: 20px 25px;
    text-align: center;
    min-height: 180px; /* Fixed height to prevent resizing on state change */
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    gap: 20px;
}

.popup-body p { font-size: 0.8rem; color: #888; margin: 0; }

.input-group {
    display: flex;
    gap: 10px;
    align-items: center;
    background: #111;
    padding: 8px 12px;
    border: 1px solid #333;
}

.input-wrapper {
    position: relative;
    width: 100%;
}

.prompt { color: var(--color-accent); font-size: 0.8rem; font-weight: bold; }
.input-group input {
    background: transparent;
    border: none;
    color: #fff;
    font-family: 'Microgramma', monospace;
    font-size: 1rem;
    width: 100%;
    outline: none;
}

.connection-status {
    width: 100%;
}

.connect-btn {
    background: transparent;
    border: none;
    color: var(--color-accent);
    font-family: 'Microgramma', monospace;
    cursor: pointer;
    font-size: 1.1rem;
    transition: all 0.2s;
}

.connect-btn:disabled { color: #444; cursor: not-allowed; }
.connect-btn:not(:disabled):hover { text-shadow: 0 0 10px var(--color-accent); }

.viz-canvas {
    width: 100%;
    height: 60px;
}

.dialing-text {
    color: #ff0000;
    font-size: 0.8rem;
    margin-top: 10px;
    text-transform: uppercase;
}

.scanlines-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: repeating-linear-gradient(
        to bottom,
        transparent 2px,
        transparent 2px,
        rgba(0, 0, 0, 0.4) 5px,
        rgba(0, 0, 0, 0.4) 5px
    );
    pointer-events: none;
    z-index: 10;
    opacity: 1;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@media (max-width: 900px) {
    .terminal-overlay {
        padding: 20px;
    }
    .boot-line {
        font-size: 0.8rem;
    }
    .popup-overlay {
        width: 90%;
    }
}
</style>
