<script setup>
import { ref, onMounted, onUnmounted, reactive, watch } from 'vue';
import SoundManager from '../sfx/SoundManager';
import InterferenceEffect from './Effects/InterferenceEffect.vue';

const activeKeys = reactive({
    ArrowUp: false,
    ArrowDown: false,
    ArrowLeft: false,
    ArrowRight: false,
    // Support legacy names
    Up: false,
    Down: false,
    Left: false,
    Right: false
});

let comboStarted = false;
const interferenceRef = ref(null);

const isUp = (k) => k === 'ArrowUp' || k === 'Up';
const isDown = (k) => k === 'ArrowDown' || k === 'Down';
const isLeft = (k) => k === 'ArrowLeft' || k === 'Left';
const isRight = (k) => k === 'ArrowRight' || k === 'Right';

const handleKeyDown = (e) => {
    if (activeKeys.hasOwnProperty(e.key)) {
        // Only start the combo if ArrowUp is the FIRST key to be pressed
        const noOtherKeysDown = !activeKeys.ArrowUp && !activeKeys.Up && 
                              !activeKeys.ArrowDown && !activeKeys.Down &&
                              !activeKeys.ArrowLeft && !activeKeys.Left &&
                              !activeKeys.ArrowRight && !activeKeys.Right;
        
        if (isUp(e.key) && noOtherKeysDown) {
            comboStarted = true;
        }
        activeKeys[e.key] = true;
    }
};

const handleKeyUp = (e) => {
    if (activeKeys.hasOwnProperty(e.key)) {
        activeKeys[e.key] = false;
        // If ArrowUp is released, the combo is broken
        if (isUp(e.key)) {
            comboStarted = false;
        }
    }
};

// Explosion Logic
const explosionStyles = ref([]);

// Watch for the "Konami-ish" all-arrows press
watch(activeKeys, () => {
    const up = activeKeys.ArrowUp || activeKeys.Up;
    const down = activeKeys.ArrowDown || activeKeys.Down;
    const left = activeKeys.ArrowLeft || activeKeys.Left;
    const right = activeKeys.ArrowRight || activeKeys.Right;

    if (comboStarted && up && down && left && right) {
        triggerExplosion();
        comboStarted = false; // Reset after successful trigger
    }
});

const triggerExplosion = () => {
    if (explosionStyles.value.length === 0 || explosionStyles.value.length !== titleText.value.length) {
        // Init styles if needed (should match length)
        explosionStyles.value = new Array(titleText.value.length).fill({});
    }

    // Explode Sound
    SoundManager.playGlitchSound(); 
    
    // Trigger Interference Effect
    if (interferenceRef.value) {
        // Target specifically the title container or span
        // Note: The title is made of spans, but we want to explode the "Word" visual.
        // We can pass the parent .title element's font info.
        const titleEl = document.querySelector('.hero-display .title');
        
        if (titleEl) {
            const rect = titleEl.getBoundingClientRect();
            const computedStyle = window.getComputedStyle(titleEl);
            
            // Construct font string manually for Canvas
            const font = `${computedStyle.fontWeight} ${computedStyle.fontSize} ${computedStyle.fontFamily}`;
            
            interferenceRef.value.explodeText(rect, "OWNEDGE", { font });
        }
    }
    
    // Hide Text Instantly
    explosionStyles.value = titleText.value.split('').map(() => ({
        opacity: 0,
        transition: 'opacity 0.05s linear'
    }));

    // Reassemble (Bounce Back)
    setTimeout(() => {
       explosionStyles.value = titleText.value.split('').map(() => ({
            transform: 'translate(0, 0)',
            opacity: 1,
            transition: 'all 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275)' 
       }));
    }, 1500); // Wait for particles to mostly dissipate
};

const titleFull = "OWNEDGE";
const titleText = ref("");

const subtitles = [
  "INDEPENDENT BY DESIGN...",
];

const displayedText = ref("");
let currentSubtitleIndex = 0;
let charIndex = 0;
let isDeleting = false;
let typingTimeout = null;
let titleTimeout = null;
const isSubtitleActive = ref(false); // New state to control subtitle start

const chars = "AЯCDEFGHIJKLДMNOPQRЯSTUVWXYZ0123456789";

const heroRoot = ref(null);
const isVisible = ref(true);
let observer = null;

const decodeEffect = () => {
  let iterations = 0;
  
  // Clear any existing timeout just in case
  clearTimeout(titleTimeout);

  titleTimeout = setInterval(() => {
    titleText.value = titleFull
      .split("")
      .map((letter, index) => {
        if (index < iterations) {
          return titleFull[index];
        }
        // Play grit sound for active decoding
        if (isVisible.value) SoundManager.playDecodeSound();
        return chars[Math.floor(Math.random() * chars.length)];
      })
      .join("");

    if (iterations >= titleFull.length) { 
      clearInterval(titleTimeout);
      // Wait a moment for impact before starting subtitles
      setTimeout(() => {
        isSubtitleActive.value = true;
        if (isVisible.value) typeWriter();
      }, 200);
    }
    
    iterations += 1 / 3; 
  }, 50);
}

const typeWriter = () => {
  if (!isSubtitleActive.value || !isVisible.value) return; 

  const currentSubtitle = subtitles[0]; // Always use the first one

  if (charIndex < currentSubtitle.length) {
    displayedText.value += currentSubtitle.charAt(charIndex);
    charIndex++;
    SoundManager.playTypingSound();
    
    // Continue typing
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(typeWriter, 100);
  } else {
    // Finished typing. Do nothing (cursor blinks via CSS).
  }
};

onMounted(() => {
  // Setup Intersection Observer
  observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
          isVisible.value = entry.isIntersecting;
          
          if (isVisible.value && isSubtitleActive.value) {
              // Reset and Restart Animation when re-entering view
              clearTimeout(typingTimeout); 
              charIndex = 0;
              displayedText.value = "";
              typeWriter();
          }
      });
  }, { threshold: 0.1 });

  if (heroRoot.value) observer.observe(heroRoot.value);

  // Start decode effect immediately
  decodeEffect();
  
  window.addEventListener('keydown', handleKeyDown);
  window.addEventListener('keyup', handleKeyUp);
});

onUnmounted(() => {
  if (observer) observer.disconnect();
  clearTimeout(typingTimeout);
  clearInterval(titleTimeout);
  window.removeEventListener('keydown', handleKeyDown);
  window.removeEventListener('keyup', handleKeyUp);
});
</script>

<template>
  <div class="hero-display" ref="heroRoot">
    <div class="content">
      <h1 class="title">
        <span class="typing-wrapper">
          <span 
            v-for="(char, i) in titleText" 
            :key="i" 
            class="char-span"
            :style="explosionStyles[i]"
          >{{ char }}</span>
        </span>
      </h1>
      <div class="large-counter" aria-hidden="true">
        <img src="../assets/ownedge-logo.png" alt="Ownedge Logo" class="logo-img" />
      </div>
      
      <p class="subtitle">
        <span class="typing-wrapper" v-if="isSubtitleActive">
          {{ displayedText }}<span class="cursor">█</span>
        </span>
      </p>
    </div>
    
    <div class="scroll-indicator">
      <div class="mouse-icon"></div>
      <div class="arrow-scroll"></div>
    </div>

    <div class="nav-hint">
        <svg class="keyboard-svg" viewBox="0 0 280 160" fill="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <g id="big-iso-key">
                    <path d="M10 20 L50 40 L90 20 L50 0 Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M10 20 L10 35 L50 55 L90 35 L90 20" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                    <path d="M50 55 L50 40" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                </g>
            </defs>

            <!-- UP KEY (Centered on top row) -->
            <g transform="translate(139, 39)">
                <g class="key-inner" :class="{ active: activeKeys.ArrowUp || activeKeys.Up }">
                    <use href="#big-iso-key" />
                    <path d="M40 25 L60 15" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    <path d="M50 14 L60 15 L56 24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
            </g>

            <!-- LEFT KEY (Bottom row left) -->
            <g transform="translate(40, 40)">
                <g class="key-inner" :class="{ active: activeKeys.ArrowLeft || activeKeys.Left }">
                    <use href="#big-iso-key" />
                    <path d="M60 25 L40 15" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    <path d="M50 14 L40 15 L44 24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
            </g>

            <!-- DOWN KEY (Bottom row center) -->
            <g transform="translate(90, 65)">
                <g class="key-inner" :class="{ active: activeKeys.ArrowDown || activeKeys.Down }">
                    <use href="#big-iso-key" />
                    <path d="M60 15 L40 25" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    <path d="M50 26 L40 25 L44 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
            </g>

            <!-- RIGHT KEY (Bottom row right) -->
            <g transform="translate(140, 90)">
                <g class="key-inner" :class="{ active: activeKeys.ArrowRight || activeKeys.Right }">
                    <use href="#big-iso-key" />
                    <path d="M40 15 L60 25" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    <path d="M50 26 L60 25 L56 16" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
            </g>
        </svg>
        <div class="hint-label">Keyboard<span class="key-label">ON</span></div>
    </div>
    
    <InterferenceEffect ref="interferenceRef" />
  </div>
</template>

<style scoped>

.hero-display {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  width: 100%;
  z-index: 20;
  position: relative;
}

.content {
  text-align: center;
  position: relative;
  margin-top: 10vh;
}

.typing-wrapper {
  display: inline-block;
  position: relative;
  min-height: 1.2rem; /* Match subtitle font size/line-height to prevent collapse */
  vertical-align: bottom;
}

.title {
  font-size: 8vw;
  font-weight: normal;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  margin-bottom: 20px;
  color: #fff;
  text-shadow: 
    0 0 10px rgba(255,255,255,0.3),
    0 0 20px rgba(255,255,255,0.3),
    0 0 30px rgba(255,255,255,0.3);
  /* Removed animation: fadeInUp since we are typing it now */
  min-height: 1.2em; /* Prevent layout shift */
  display: flex;       /* Added centered flex for the spans */
  justify-content: center; 
}

.char-span {
    display: inline-block;
    white-space: pre;
    will-change: transform, opacity;
}

/* Rest of styles remain mostly same, just ensuring fadeInUp is not conflicting */
.large-counter {
  font-size: 25vw;
  line-height: 1;
  color: rgba(255, 255, 255, 0.03);
  position: absolute;
  top: 40%;
  left: 50%;
  transform: translate(-50%, -50%) scaleX(1.08); /* 👈 stretch 10% horizontally */
  z-index: -1;
  font-family: var(--font-mono);
  font-weight: bold;
  opacity: 0;
  animation: fadeIn 3s ease-in-out 0.3s forwards;
}

.logo-img {
  width: 25vw;
  height: auto;
  opacity: 0.09; 
  display: block;
  filter: brightness(0) invert(1) drop-shadow(0 0 10px rgba(255,255,255,0.1));
  transition: all 1s ease;
  pointer-events: auto; 
}

.subtitle {
  font-size: 1.4rem;
  letter-spacing: 0.14em;
  color: var(--color-accent);
  text-transform: uppercase;
  /* Removed animation: fadeInUp/opacity:0 because it needs to be visible for typing */
  text-shadow: 0 0 10px var(--color-accent), 0 0 20px var(--color-accent);
  min-height: 1.5em; 
}

.cursor {
  display: inline-block;
  position: absolute;
  left: 100%;
  bottom: 0;
  animation: blink 1s step-end infinite;
  color: var(--color-accent);
  text-shadow: 0 0 10px var(--color-accent);
  margin-left: 2px;
  width: 1ch; /* Ensure it has width to render even if absolute */
}

/* Ensure title cursor is white though */
.title .cursor {
    color: #fff;
    text-shadow: 0 0 10px #fff;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0; }
}

@keyframes fadeIn {
  from { 
    opacity: 0; 
  }
  to { 
    opacity: 1; 
  }
}

.scroll-indicator {
  position: absolute;
  bottom: 90px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  opacity: 0;
  animation: fadeIn 1s ease-out 4s forwards; /* Delay appearance until after title decode */
  pointer-events: none;
}

.scroll-text {
  font-size: 0.7rem;
  letter-spacing: 0.2em;
  color: var(--color-accent);
  text-shadow: 0 0 5px var(--color-accent);
  opacity: 0.7;
}

.arrow-scroll {
  width: 15px;
  height: 15px;
  border-right: 2px solid #fff;
  border-bottom: 2px solid #fff;
  transform: rotate(45deg);
  animation: scrollBounce 2s infinite;
}

@keyframes scrollBounce {
  0% { transform: rotate(45deg) translate(0, 0); opacity: 0; }
  50% { opacity: 1; }
  100% { transform: rotate(45deg) translate(10px, 10px); opacity: 0; }
}
@media (max-width: 900px) {
  .title {
    font-size: 12vw;
    margin-bottom: 10px;
  }
  .subtitle {
    font-size: 1.0rem;
  }
  .content {
    margin-top: -5vh;
  }
  .logo-img {
    width: 45vw;
  }
  .scroll-indicator {
    bottom: 30px;
  }
}

.nav-hint {
    position: absolute;
    bottom: 0px;
    right: 40px;
    font-family: var(--font-mono);
    color: var(--color-accent);
    opacity: 0;
    animation: fadeInHint 1s ease-out 3.5s forwards;
    pointer-events: none;
    z-index: 50;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}

.keyboard-svg {
    width: 120px;
    height: auto;
    stroke: var(--color-accent);
    filter: drop-shadow(0 0 2px var(--color-accent));
    /* Subtle breathing animation for keys */
    animation: pulseKeys 4s ease-in-out infinite 5s;
}

.hint-label {
    font-size: 0.7rem;
    font-family: 'Microgramma', 'Courier New', monospace;
    letter-spacing: 0.2rem;
    font-weight: bold;
    text-shadow: 0 0 5px var(--color-accent);
}

.key-label {
    color: rgba(255, 255, 255, 0.99);
    font-family: 'Microgramma', 'Courier New', monospace;
    display: inline-block;
    padding: 2px 6px;
    border-radius: 1px;
    background: rgba(255, 255, 255, 0.2);
    letter-spacing: 0.1rem;
}

@keyframes fadeInHint {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 0.8; transform: translateY(0); }
}

@keyframes pulseKeys {
    0%, 100% { filter: drop-shadow(0 0 2px var(--color-accent)); }
    50% { filter: drop-shadow(0 0 8px var(--color-accent)); }
}

@media (max-width: 900px) {
    .nav-hint {
        display: none;
    }
}

.key-inner {
    transition: transform 0.15s ease-out;
}
.key-inner.active {
    transform: translateY(4px);
    transition: transform 0.1s ease-in;
    filter: drop-shadow(0 0 8px var(--color-accent));
}
</style>
