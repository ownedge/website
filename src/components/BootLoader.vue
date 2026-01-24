<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue';
import SoundManager from '../sfx/SoundManager';
import { chatStore } from '../store/chatStore';
import { keyboardStore } from '../store/keyboardStore';

const videoRef = ref(null);
const canvasRef = ref(null);
const introChoice = ref('yes'); // 'yes' or 'no'
const transitionName = ref('slide-next');
const nicknameChars = ref([]);

const selectIntroOption = (choice) => {
    if (choice === introChoice.value) return;
    
    // yes (0) -> no (1) : Next/Right
    // no (1) -> yes (0) : Prev/Left
    if (introChoice.value === 'yes' && choice === 'no') {
        transitionName.value = 'slide-next';
    } else {
        transitionName.value = 'slide-prev';
    }

    introChoice.value = choice;
    SoundManager.playTypingSound();
};

const confirmIntroOption = (choice) => {
    if (choice !== introChoice.value) {
        selectIntroOption(choice);
        // Wait for slide animation (approx 400ms) before connecting
        setTimeout(() => {
            handleConnect();
        }, 450);
    } else {
        handleConnect();
    }
};

const toggleIntroOption = () => {
    const newChoice = introChoice.value === 'yes' ? 'no' : 'yes';
    selectIntroOption(newChoice);
};

const props = defineProps({
  isBooted: { type: Boolean, default: false }
});
const emit = defineEmits(['start', 'progress', 'ready', 'connecting', 'status-update', 'skip']);

const bootStage = ref('bios'); // bios, intro, connecting, ready
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

const CHARS = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_ ";
const lastCharIndex = ref(0);
// const hiddenInput = ref(null); -- Removed

const addNicknameChar = (key) => {
    if (nicknameChars.value.length >= 8) return;

    const targetChar = key.toUpperCase();
    const targetIndex = CHARS.indexOf(targetChar);
    
    // Calculate Rotation
    let diff = targetIndex - lastCharIndex.value;
    
    // Handle collision (start/end on same face)
    if (diff !== 0 && diff % 4 === 0) {
        // Offset by 1 step in the direction of travel to avoid collision
        diff += (diff > 0) ? -1 : 1;
    }
    
    const startRotationDeg = -(diff * 90);
    
    // Calculate Duration based on distance
    const steps = Math.abs(diff);
    const durationSec = Math.min(0.3 + (steps * 0.02), 1.0);
    
    // Map Faces
    let startFaceIdx = diff % 4;
    if (startFaceIdx < 0) startFaceIdx += 4;
    
    const faces = {
        front: getRandomChar(),
        bottom: getRandomChar(),
        back: getRandomChar(),
        top: getRandomChar()
    };
    
    // Set Start Char
    const faceKeys = ['front', 'bottom', 'back', 'top'];
    const startChar = CHARS[lastCharIndex.value];
    faces[faceKeys[startFaceIdx]] = startChar;
    
    // Set Final Char (Always Front)
    faces.front = targetChar;
    
    const charObj = {
        final: targetChar,
        faces: faces,
        startRot: `rotateX(${startRotationDeg}deg)`,
        duration: `${durationSec}s`,
        landed: false
    };
    
    nicknameChars.value.push(charObj);
    lastCharIndex.value = targetIndex;
    
    // Capture the reactive proxy immediately
    const itemToAnimate = nicknameChars.value[nicknameChars.value.length - 1];

    // Trigger animation
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            itemToAnimate.landed = true;
        });
    });
    
    SoundManager.playTypingSound();
};

const triggerKeyboard = () => {
    // Open Global Virtual Keyboard
    keyboardStore.open((key) => {
        if (key === 'BACKSPACE') {
             if (nicknameChars.value.length > 0) {
                nicknameChars.value.pop();
                if (nicknameChars.value.length > 0) {
                    const last = nicknameChars.value[nicknameChars.value.length - 1];
                    lastCharIndex.value = CHARS.indexOf(last.final);
                } else {
                    lastCharIndex.value = 0;
                }
            }
        } else if (key === 'ENTER') {
           // Do nothing or confirm?
           keyboardStore.close(); 
           handleConnect();
        } else if (key === ' ') {
            // Space
             if (/[a-zA-Z0-9\-_ ]/.test(key)) {
                addNicknameChar(key);
            }
        } else {
             if (/[a-zA-Z0-9\-_ ]/.test(key)) {
                addNicknameChar(key);
            }
        }
    }, () => {
        // On Close callback if needed
    });
};

/* Removed old handleMobileInput */

const handleIntroKeydown = (e) => {
    if (props.isBooted || bootStage.value !== 'intro') return;
    
    // If typing in the hidden input, ignore CHARACTERS (handled by @input)
    // but allow Backspace, Enter, and Arrows to pass through.
    // if (e.target.tagName === 'INPUT') ... -- Removed as we don't use hidden input anymore
    
    // Navigation
    if (e.key === 'ArrowRight' || e.key === 'Right') {
        selectIntroOption('no');
        return;
    } else if (e.key === 'ArrowLeft' || e.key === 'Left') {
        selectIntroOption('yes');
        return;
    } else if (e.key === 'Enter') {
        SoundManager.playTypingSound();
        handleConnect();
        return;
    }

    // Nickname Entry
    if (e.key === 'Backspace') {
        if (nicknameChars.value.length > 0) {
            nicknameChars.value.pop();
            
            // Re-calc last index
            if (nicknameChars.value.length > 0) {
                const last = nicknameChars.value[nicknameChars.value.length - 1];
                lastCharIndex.value = CHARS.indexOf(last.final);
            } else {
                lastCharIndex.value = 0;
            }
            SoundManager.playTypingSound();
        }
    } else if (e.key.length === 1 && /[a-zA-Z0-9\-_]/.test(e.key)) {
        addNicknameChar(e.key);
    }
};

const getRandomChar = () => {
    return CHARS.charAt(Math.floor(Math.random() * CHARS.length));
};

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

const fetchGeoInfo = async () => {
    try {
        const controller = new AbortController();
        const id = setTimeout(() => controller.abort(), 3000); // 3s timeout
        const res = await fetch('https://ipwho.is/', { signal: controller.signal });
        clearTimeout(id);
        if (!res.ok) throw new Error('API Error');
        return await res.json();
    } catch (e) {
        return null;
    }
};

const runBiosSequence = async () => {
    emit('progress', 0);
    await addMessage('> BIOS VERSION 4.2.Ø (C) 2011 OWNEDGE CORP', 500);
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
    await addMessage('> DRIVE Ø: MOUNTING CORE_OS... ✔', 200);
    
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
    
    // Fetch IP and Location
    const geoData = await fetchGeoInfo();
    let ipDisplay = '127.0.0.1';
    let locDisplay = 'NET_ERR';

    if (geoData && geoData.ip) {
        ipDisplay = geoData.ip;
        // Construct location: City, Country
        const parts = [];
        if (geoData.city) parts.push(geoData.city);
        if (geoData.country_name) parts.push(geoData.country_name); // or country_code
        if (parts.length > 0) locDisplay = parts.join(', ').toUpperCase();
        else locDisplay = 'UNKNOWN_LOC';
        
        // Store in chatStore for later use
        chatStore.userIp = geoData.ip;
        chatStore.userLocation = locDisplay;
        chatStore.userLat = geoData.latitude;
        chatStore.userLon = geoData.longitude;
    }
    
    await addMessage(`> IP ADDRESS: ${ipDisplay}`, 100);
    if (locDisplay !== 'NET_ERR') {
        await addMessage(`> LOCATION DETECTED: ${locDisplay}`, 100);
    }
    
    emit('progress', 100);
    emit('ready'); // VFD is now ready for interaction
    
    bootStage.value = 'intro';
    window.addEventListener('keydown', handleIntroKeydown);

    // Auto-type stored nickname if exists and not guest
    if (chatStore.nickname && !chatStore.nickname.startsWith('guest-')) {
        await new Promise(r => setTimeout(r, 800)); // Delay for popup animation
        const nickToType = chatStore.nickname.substring(0, 8);
        for (const char of nickToType) {
            // Stop if user skipped or stage changed
            if (props.isBooted || bootStage.value !== 'intro') break;
            
            // Check if char is valid for our carousel (alphanumeric+dash+underscore)
            if (/[a-zA-Z0-9\-_]/.test(char)) {
                addNicknameChar(char);
                await new Promise(r => setTimeout(r, 150));
            }
        }
        // Open keyboard after auto-typing for confirmation/editing
        if (window.innerWidth <= 900) {
            triggerKeyboard();
        }
    } else {
        // If no stored nickname, open virtual keyboard on mobile
        if (window.innerWidth <= 900) {
            setTimeout(triggerKeyboard, 1000); // Delay for popup transition
        }
    }
};

const handleConnect = async () => {
    if (isConnecting.value) return;
    
    // Fill remaining slots with blocks (Space) for visual effect
    while (nicknameChars.value.length < 8) {
        addNicknameChar(' ');
        await new Promise(r => setTimeout(r, 60));
    }

    // Remove listener
    window.removeEventListener('keydown', handleIntroKeydown);
    
    const finalNick = nicknameChars.value.map(c => c.final).join('').trim().toLowerCase();
    
    // Auto-generate nickname since we don't ask
    if (finalNick !== '') {
        chatStore.nickname = finalNick;
    } else if (!chatStore.nickname || chatStore.nickname.trim() === '') {
        const rand = Math.floor(1000 + Math.random() * 9000);
        chatStore.nickname = `guest-${rand}`;
    }

    if (introChoice.value === 'no') {
       // Minimal boot if declined (skips visualization)
       handleSkip(); 
       return;
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

    // Close keyboard if open (mobile)
    if (window.innerWidth <= 900) {
        keyboardStore.close();
    }

    setTimeout(() => {
        emit('start');
    }, 800);
};

const handleSkip = () => {
    window.removeEventListener('keydown', handleIntroKeydown);
    // Close keyboard if open (mobile)
    if (window.innerWidth <= 900) {
        keyboardStore.close();
    }
    emit('skip');
};

const startVisualization = (retries = 0) => {
    if (!canvasRef.value) {
        if (retries < 5) {
            setTimeout(() => startVisualization(retries + 1), 50);
        }
        return;
    }
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
  
  // Pre-load SoundManager? No, wait for user interaction or auto-sequence if possible.
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
  window.removeEventListener('keydown', handleIntroKeydown);
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
          <transition name="popup-reveal">
            <div v-if="bootStage === 'intro' || bootStage === 'connecting'" class="popup-overlay">
              <div class="popup-header">
                <span>REMOTE NODE LINK</span>
                <div class="esc-label" @click="handleSkip">ESC</div>
              </div>
              <div class="popup-body">
                <transition name="fade" mode="out-in">
                    <div v-if="bootStage === 'intro'" class="cookies-container" key="intro">
                        <div class="char-grid-container" @click="triggerKeyboard">
                            <div class="char-label">
                                NICKNAME 
                            </div>
                            <div class="char-grid">
                                <div 
                                    v-for="i in 8" 
                                    :key="i" 
                                    class="char-box"
                                    :class="{ 'active': nicknameChars.length === i-1 }"
                                >
                                    <div 
                                        v-if="nicknameChars[i-1]" 
                                        class="cube" 
                                        :class="{ landed: nicknameChars[i-1].landed }"
                                        :style="{ 
                                            transform: nicknameChars[i-1].landed ? 'rotateX(0deg)' : nicknameChars[i-1].startRot, 
                                            transitionDuration: nicknameChars[i-1].duration 
                                        }"
                                    >
                                        <div class="cube-face front">{{ nicknameChars[i-1].faces.front }}</div>
                                        <div class="cube-face back">{{ nicknameChars[i-1].faces.back }}</div>
                                        <div class="cube-face top">{{ nicknameChars[i-1].faces.top }}</div>
                                        <div class="cube-face bottom">{{ nicknameChars[i-1].faces.bottom }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        


                        <p>WATCH INTRO?</p>
                        
                        <div class="cookie-select-container">
                            <div 
                                class="highlight-block" 
                                :class="[introChoice === 'yes' ? 'left' : 'right']"
                            ></div>
                            <div 
                                class="cookie-option" 
                                :class="{ 'active-text': introChoice === 'yes' }"
                                @click="confirmIntroOption('yes')"
                            >YES</div>
                            <div 
                                class="cookie-option" 
                                :class="{ 'active-text': introChoice === 'no' }"
                                @click="confirmIntroOption('no')"
                            >NO</div>
                        </div>
                        
                    </div>

                    <div v-else-if="bootStage === 'connecting'" class="connection-status" key="connecting">
                      <canvas ref="canvasRef" width="300" height="60" class="viz-canvas"></canvas>
                      <div class="dialing-text">ESTABLISHING HANDSHAKE...</div>
                    </div>
                </transition>
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
    font-family: 'Microgramma', monospace;
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
    min-height: 208px; /* Fixed height to prevent resizing on state change */
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

.cookies-container,
.connection-status {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
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

.popup-reveal-enter-active {
  animation: popup-open 0.6s cubic-bezier(0.23, 1, 0.32, 1) forwards;
}

.popup-reveal-leave-active {
  transition: opacity 0.3s ease;
  opacity: 0;
}

@keyframes popup-open {
    0% { opacity: 0; clip-path: inset(49% 0 49% 0); }
    30% { opacity: 1; clip-path: inset(49% 0 49% 0); }
    100% { opacity: 1; clip-path: inset(0 0 0 0); }
}

/* Delay content appearance to let box expand first */
.popup-reveal-enter-active .popup-body,
.popup-reveal-enter-active .popup-header {
    animation: simple-fade 0.3s 0.3s backwards;
}

@keyframes simple-fade {
    from { opacity: 0; }
    to { opacity: 1; }
}

.mobile-input-trap {
    position: absolute;
    opacity: 0;
    top: 0;
    left: 0;
    height: 1px;
    width: 1px;
    pointer-events: none;
    z-index: -1;
}

.mobile-keyboard-btn {
    display: none; /* Hidden by default (Desktop) */
    font-size: 0.55rem;
    color: var(--color-accent);
    background: rgba(64, 224, 208, 0.1);
    border: 0.5px solid var(--color-accent);
    padding: 2px 8px;
    cursor: pointer;
    margin-left: 10px;
    font-family: 'Microgramma', monospace;
    letter-spacing: 1px;
    transition: all 0.2s;
    vertical-align: middle;
}

.mobile-keyboard-btn:active {
    background: var(--color-accent);
    color: #000;
}

.kb-icon {
    font-size: 0.9rem;
    margin-right: 5px;
}



@media (max-width: 900px) {
    .mobile-keyboard-btn {
        display: inline-flex;
        align-items: center;
    }
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

/* Side-by-Side Cookie Selection */
.cookie-select-container {
    position: relative;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    background: rgba(0,0,0,0.5);
    height: 40px; /* Fixed height for the block calculation */
    width: 200px; /* Fixed width for the block calculation */
}

.highlight-block {
    position: absolute;
    top: 2px;
    bottom: 2px;
    width: 40%;
    background: var(--color-accent);
    /* "Crazy" Elastic: Big wind-up (-0.8) and big overshoot (1.9) */
    transition: left 0.4s cubic-bezier(0.5, -0.8, 0.5, 1.9), transform 0.3s ease-in-out, border-radius 0.3s ease;
    z-index: 1;
    box-shadow: 0 0 10px rgba(64, 224, 208, 0.5);
    animation: glitch-jitter 4s infinite;
}

/* Position block based on choice */
.highlight-block.left {
    left: 5%;
    transform: skewX(-10deg);
    border-radius: 3px 6px 4px 2px; /* Square-ish but imperfect */
}
.highlight-block.right {
    left: 55%;
    transform: skewX(10deg);
    border-radius: 6px 3px 2px 5px; /* Square-ish but imperfect */
}

@keyframes glitch-jitter {
    0%, 92% { clip-path: inset(0 0 0 0); opacity: 1; }
    93% { clip-path: inset(10% 0 30% 0); opacity: 0.8; }
    95% { clip-path: inset(40% 0 10% 0); opacity: 1; }
    98% { clip-path: inset(0 0 50% 0); opacity: 0.9; }
    100% { clip-path: inset(0 0 0 0); opacity: 1; }
}

.cookie-option {
    position: relative;
    z-index: 2;
    flex: 1; /* Each takes 50% */
    text-align: center;
    cursor: pointer;
    font-size: 1.1rem;
    font-weight: bold;
    color: var(--color-accent);
    transition: color 0.3s ease;
    user-select: none;
    line-height: 36px; /* Center vertically in 40px container (minus padding) */
}

/* Invert text color when active */
.cookie-option.active-text {
    color: #000;
}

.arrow-indicator {
    display: none; /* Hide arrows in this mode */
}

.arrow-indicator {
    display: none; /* Hide arrows in this mode */
}

.char-grid-container {
    margin-bottom: 15px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.char-label {
    font-size: 0.8rem;
    color: #888;
    margin: 0;
    margin-bottom: 5px;
}

.char-grid {
    display: flex;
    gap: 6px;
}

.char-box {
    width: 32px;
    height: 42px;
    border: 1px solid rgba(0,0,0,0.4);
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: 'Microgramma', monospace;
    font-size: 1.2rem;
    color: var(--color-accent);
    text-transform: uppercase;
    background: var(--color-accent);
    transition: all 0.2s;
    box-shadow: inset 0 0 5px rgba(0,0,0,0.2);
    perspective: 400px; /* 3D Scene */
    overflow: hidden; /* Clip anything sticking out */
}

.cube {
    width: 100%;
    height: 100%;
    position: relative;
    transform-style: preserve-3d;
    transform: rotateX(0deg);
    transition-property: transform;
    transition-timing-function: cubic-bezier(0.34, 1.56, 0.64, 1);
}

.cube.landed {
    transform: rotateX(0deg); /* Land on front */
}

.cube-face {
    position: absolute;
    width: 32px;
    height: 42px;
    background: var(--color-accent);
    color: #000;
    font-weight: bold;
    border: 1px solid rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    backface-visibility: hidden;
    /* Center in 3D space */
    left: -1px; /* Account for parent border alignment */
    top: -1px;  /* Account for parent border alignment */
}

/* 
Height is 42px. 
Radius (translateZ) = 42/2 = 21px.
*/
.cube-face.front  { transform: rotateX(0deg) translateZ(21px); }
.cube-face.back   { transform: rotateX(180deg) translateZ(21px); }
.cube-face.top    { transform: rotateX(90deg) translateZ(21px); }
.cube-face.bottom { transform: rotateX(-90deg) translateZ(21px); }

.char-box.active {
    animation: blink-cursor 1s infinite;
}

@keyframes blink-cursor {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.1; }
}
</style>
