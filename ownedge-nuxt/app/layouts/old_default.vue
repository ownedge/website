<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import SoundManager from '~/utils/sfx/SoundManager'
import { SYSTEM_CONFIG } from '~/utils/config'
import { chatStore } from '~/stores/chatStore'
import GlobalSvgFilters from '~/components/GlobalSvgFilters.vue'

// Auto-imported Components (Nuxt):
// GridOverlay, TrackerOverlay, BootLoader, VfdDisplay, CrtControls, BezelReflection

const windowWidth = ref(1024)
const windowHeight = ref(768)
const mouseX = ref(512)
const mouseY = ref(384)
const screenRect = ref(null)

// --- Boot State ---
const isCrawler = () => {
    if (import.meta.server) return true; // Always crawl-ready on server
    const ua = navigator.userAgent.toLowerCase();
    return /googlebot|bingbot|yandex|baiduspider|twitterbot|facebookexternalhit|rogerbot|linkedinbot|embedly|quora link preview|showyoubot|outbrain|pinterest\/0\.|pinterestbot|slackbot|vkShare|W3C_Validator|whatsapp/.test(ua);
};

const isBooted = ref(false) // Will init in onMounted
const vfdBootState = ref('loading')
const vfdStatusText = ref('wait')
const bootProgress = ref(0)
const vfdMode = ref('spectrum')
const vfdKnobInfo = ref({ label: '', value: '' })
let previousVfdMode = 'spectrum';

// --- CRT Settings ---
const SETTINGS_KEY = 'crt_settings'
const NICKNAME_KEY = 'chat_nickname'

// Default values (will hydrate from localStorage)
const volume = ref(SYSTEM_CONFIG.AUDIO.MASTER_VOL)
const brightness = ref(SYSTEM_CONFIG.VISUALS.BRIGHTNESS_DEFAULT)
const contrast = ref(SYSTEM_CONFIG.VISUALS.CONTRAST_DEFAULT)
const isCapsLock = ref(false)
const isTurbo = ref(true)
const isHddActive = ref(false)

// --- Selection Logic ---
const isSelecting = ref(false)
const startSelectionX = ref(0)
const startSelectionY = ref(0)
const currentSelectionX = ref(0)
const currentSelectionY = ref(0)

const updateScreenRect = () => {
    if (import.meta.server) return;
    const el = document.querySelector('.app-container');
    if (el) {
        // void el.offsetHeight;
        screenRect.value = el.getBoundingClientRect();
    }
};

const handleMouseMove = (e) => {
    mouseX.value = e.clientX
    mouseY.value = e.clientY
    if (!screenRect.value) updateScreenRect();
    if (isSelecting.value) {
        currentSelectionX.value = e.clientX - (screenRect.value?.left || 0);
        currentSelectionY.value = e.clientY - (screenRect.value?.top || 0);
    }
    
    // Parallax logic for Hero is handled in HeroDisplay itself or via CSS var injection?
    // App.vue passed mouse coords via heroStyle. We might need to provide this to children.
    // Ideally, we provide it via provide/inject or Pinia.
    // For now, let's just update CSS variables on the root?
    // Or we keep it simple. HeroDisplay used to receive it via prop or style?
    // In App.vue: <section ... :style="heroStyle">
    // We can expose `mouseX` via provide.
}

// --- Lifecycle ---
onMounted(async () => {
    windowWidth.value = window.innerWidth;
    windowHeight.value = window.innerHeight;
    isBooted.value = isCrawler();

    // Load Settings
    try {
        const saved = localStorage.getItem(SETTINGS_KEY);
        if (saved) {
            const parsed = JSON.parse(saved);
            volume.value = parsed.volume ?? volume.value;
            brightness.value = parsed.brightness ?? brightness.value;
            contrast.value = parsed.contrast ?? contrast.value;
        }
        chatStore.nickname = localStorage.getItem(NICKNAME_KEY) || '';
    } catch {}

    // Init Audio
    SoundManager.setMasterVolume(volume.value);

    // Event Listeners
    window.addEventListener('mousemove', handleMouseMove);
    window.addEventListener('mouseup', () => isSelecting.value = false);
    window.addEventListener('resize', () => {
        windowWidth.value = window.innerWidth;
        windowHeight.value = window.innerHeight;
        updateScreenRect();
    });
    window.addEventListener('keydown', (e) => {
        if (e.getModifierState) isCapsLock.value = e.getModifierState('CapsLock');
        
        // Escape Logic
        if (e.key === 'Escape') {
             if (!isBooted.value) handleBootSkip();
             // else reload? logic handled in Page?
        }
    });
    window.addEventListener('mousedown', (e) => {
        // Selection Start
        if (!isBooted.value) return;
        if (e.target.matches('button, a, input, textarea, [role="button"]')) return;
        
        isSelecting.value = true;
        updateScreenRect();
        startSelectionX.value = e.clientX - (screenRect.value?.left || 0);
        startSelectionY.value = e.clientY - (screenRect.value?.top || 0);
        currentSelectionX.value = startSelectionX.value;
        currentSelectionY.value = startSelectionY.value;
    });

    simulateHddActivity();
    triggerGlitch();
    
    nextTick(() => updateScreenRect());
})

onUnmounted(() => {
    // Cleanup listeners
})

// --- Watchers ---
watch([volume, brightness, contrast], () => {
    localStorage.setItem(SETTINGS_KEY, JSON.stringify({
        volume: volume.value,
        brightness: brightness.value,
        contrast: contrast.value
    }));
});

watch(() => chatStore.nickname, (n) => localStorage.setItem(NICKNAME_KEY, n));

// --- Actions ---
const handleBootStart = async () => {
    isBooted.value = true;
    vfdBootState.value = 'complete';
    try {
        if (!SoundManager.initialized) SoundManager.init();
        await SoundManager.resume();
        SoundManager.playBootSequence();
        SoundManager.loadVisualizer('/music/impulse.s3m');
        setTimeout(() => SoundManager.playTrackerMusic('/music/impulse.s3m'), 3800);
    } catch (e) {}
    
    vfdMode.value = 'logo';
    setTimeout(() => vfdMode.value = 'spectrum', 2100);
}

const handleBootSkip = async () => {
    isBooted.value = true;
    vfdBootState.value = 'complete';
    vfdMode.value = 'spectrum';
    try {
        if (!SoundManager.initialized) SoundManager.init();
        await SoundManager.resume();
        SoundManager.loadVisualizer('/music/impulse.s3m');
        SoundManager.playTrackerMusic('/music/impulse.s3m');
    } catch (e) {}
}

const simulateHddActivity = () => {
    if (Math.random() > 0.7) {
        isHddActive.value = true;
        SoundManager.playHddSound();
        setTimeout(() => isHddActive.value = false, 50 + Math.random() * 100);
    }
    setTimeout(simulateHddActivity, 50 + Math.random() * 200);
};

const turbulenceFreq = ref(0.0002);
const triggerGlitch = () => {
    const spike = () => {
       turbulenceFreq.value = 0.0044 * Math.random();
       setTimeout(() => turbulenceFreq.value = 0.0002, 50 + Math.random() * 100);
    };
    spike();
    if (Math.random() > 0.5) setTimeout(spike, 150);
    setTimeout(triggerGlitch, Math.random() * 8000 + 2000); 
};


// Controls Hander
const handleKnobStart = ({ type, value }) => {
    if (vfdMode.value !== 'knob') {
        previousVfdMode = vfdMode.value;
        vfdMode.value = 'knob';
    }
    updateKnobText(type, value);
};
const handleKnobChange = ({ type, value }) => {
    if (type === 'vol') SoundManager.setMasterVolume(value);
    updateKnobText(type, value);
};
const handleKnobEnd = () => {
    if (vfdMode.value === 'knob') vfdMode.value = previousVfdMode;
};
const updateKnobText = (type, val) => {
    let label = '';
    let pct = 0;
    if (type === 'vol') { label = 'VOLUME'; pct = Math.round(val * 100); }
    else if (type === 'brt') { label = 'BRGHTNSS'; pct = Math.round((val - 0.5) * 100); }
    else if (type === 'con') { label = 'CONTRST'; pct = Math.round((val - 0.5) * 100); }
    pct = Math.max(0, Math.min(100, pct));
    vfdKnobInfo.value = { label, value: `${pct}%` };
};

const selectionBoxStyle = computed(() => {
    if (!isSelecting.value) return { display: 'none' };
    const left = Math.min(startSelectionX.value, currentSelectionX.value);
    const top = Math.min(startSelectionY.value, currentSelectionY.value);
    const width = Math.abs(currentSelectionX.value - startSelectionX.value);
    const height = Math.abs(currentSelectionY.value - startSelectionY.value);
    return { left: `${left}px`, top: `${top}px`, width: `${width}px`, height: `${height}px` };
});

const vfdLabelGlow = computed(() => {
  if (vfdMode.value === 'spectrum' || vfdBootState.value === 'loading') return '0.35';
  else if (vfdMode.value === 'logo' || vfdMode.value === 'knob') return '0.20';
  return '0.0';
});

const scanlineColor = `hsl(188, 40%, 9%)`;
const vfdBgColor = `hsl(188, 42%, 7%)`;

</script>

<template>
  <div class="crt-wrapper">
    <GlobalSvgFilters />
    <!-- Fixed Controls -->
    <CrtControls
        class="crt-controls"
        v-model:volume="volume"
        v-model:brightness="brightness"
        v-model:contrast="contrast"
        :is-caps-lock="isCapsLock"
        :is-hdd-active="isHddActive"
        :is-turbo="isTurbo"
        :power-led="true"
        @knob-start="handleKnobStart"
        @knob-change="handleKnobChange"
        @knob-end="handleKnobEnd"
    />

    <div class="vfd-label-box">
        <img src="~/assets/ownedge-logo.png" class="bezel-logo" />
        <div class="vfd-label-text">
            <div class="vfd-label-line1">VF-D74O</div>
            <div class="vfd-label-line2">SUPER</div>
        </div>
    </div>
    
    <VfdDisplay 
        class="vfd-display"
        :mode="vfdMode"
        :knob-info="vfdKnobInfo"
        :boot-state="vfdBootState"
        :boot-progress="bootProgress"
        :status-text="vfdStatusText"
        :scanline-color="vfdBgColor"
    />

    <div class="crt-screen">
      <div class="app-container">
        <!-- Bootloader Overlay -->
        <BootLoader 
          :is-booted="isBooted" 
          @start="handleBootStart"
          @skip="handleBootSkip"
          @progress="(p) => { if (!isBooted) bootProgress = p }"
          @ready="() => { if (!isBooted) vfdBootState = 'ready' }"
          @connecting="() => { if (!isBooted) vfdBootState = 'connecting' }"
          @status-update="(s) => { if (!isBooted) vfdStatusText = s }"
        />

        <!-- Selection Box -->
        <div v-if="isSelecting" class="selection-marquee" :style="selectionBoxStyle"></div>

        <!-- Fixed Background -->
        <div class="fixed-background">
            <GridOverlay />
            <TrackerOverlay />
        </div>

        <!-- Nuxt Page Content Injection -->
        <!-- Only render page content if booted, or keep it hidden? -->
        <!-- Logic in App.vue was v-if="isBooted". -->
        <!-- We can use a transition or just v-if. For SEO, simpler is better. -->
        <!-- Nuxt usually wants pages rendered for crawlers. isBooted is true for cursors. -->
        <div class="page-content-layer" style="position: absolute; top:0; left:0; width:100%; height:100%; z-index: 5;">
            <slot v-if="isBooted" />
        </div>

        <!-- Overlays -->
        <div class="scanlines"></div>
        <div class="vignette"></div>
      </div>
    </div>
    
    <TrackerOverlay class="bezel-reflection-text" style="z-index: 100;" :reflection-only="true" :screen-rect="screenRect" />
    <BezelReflection v-if="isBooted" />

    <div class="bezel-sticker">
        <img src="~/assets/corner-sticker.png" />
        <div class="sticker-wear"></div>
    </div>
    
    <!-- Inline Styles for dynamic variables -->
    <component :is="'style'">
      .app-container {
        filter: brightness({{ brightness * 1.1 }}) contrast({{ contrast }}) url(#spherical-warp);
      }
      .scanlines {
        background: radial-gradient(circle, transparent 2%, {{ scanlineColor }} 95%);
      }
    </component>
  </div>
</template>

<style>
/* CSS copied from App.vue, stripped of scoped if global */
/* Since it's a Layout, global styles might be better in assets/css/style.css, but layout-specific styles here. */

/* NOTE: Since this is NOT scoped in original (maybe it was?), I should check. */
/* Original App.vue had <style scoped>. */
/* I'll use scoped here too, but Nuxt Layout styles are usually scoped to the wrapper or used globally. */

/* CRT Wrapper */
.crt-wrapper {
  width: 100vw;
  height: 100vh;
  background-color: #050505;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 40px 80px 40px;
  position: relative;
  overflow: hidden;
  user-select: none;
}

.crt-wrapper input,
.crt-wrapper textarea,
.crt-wrapper [contenteditable="true"] {
    user-select: text;
}

.crt-wrapper::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 1;
  background-repeat: no-repeat, no-repeat, repeat, repeat, repeat, repeat, repeat, repeat;
  background-position: 95% 5%, 5% 95%, center, center, center, center, center, center;
  background-size: 400px 400px, 400px 400px, auto, auto, auto, auto, auto, auto;
  mix-blend-mode: color-dodge;
  -webkit-mask: url(#bezel-mask);
  mask: url(#bezel-mask);
}

.bezel-sticker {
    position: absolute;
    top: 8px;
    left: 11px;
    width: 40px;
    height: auto;
    z-index: 15;
    filter: contrast(0.8) brightness(1.7);
    transform: rotate(90deg);
    opacity: 0.36;
}
.bezel-sticker img { width: 100%; height: auto; display: block; }

.crt-screen {
  width: 100%;
  height: 100%;
  max-width: 100%;
  max-height: 100%;
  border-radius: 40px;
  position: relative;
  overflow: hidden;
  box-shadow: inset 0 0 80px rgba(0,0,0,0.9), 0 0 90px rgba(100, 100, 100, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  border: 11px solid transparent;
  background-clip: padding-box, border-box;
  background-origin: padding-box, border-box;
  background-image: linear-gradient(#000, #000), radial-gradient(circle at center, #333333 0%, #222222 90%);
}

.app-container {
  width: 101%;
  height: 101%;
  flex-shrink: 0;
  position: relative;
  background: radial-gradient(circle at center, #2f2f2f00 1%, #0e0e0e 90%);
  overflow: hidden;
}

.fixed-background {
  position: absolute;
  top: 0; left: 0; width: 100%; height: 100%;
  z-index: 10;
  pointer-events: none;
}

.scanlines {
  position: absolute;
  top: 0; left: 0; width: 100%; height: 100%;
  background-size: 1.5px 1.5px;
  pointer-events: none;
  z-index: 50;
  opacity: 0.75;
}

.vignette {
  position: absolute;
  top: 0; left: 0; width: 100%; height: 100%;
  pointer-events: none;
  z-index: 60;
  box-shadow: inset 0 0 100px rgba(0,0,0,0.9);
  border-radius: 5px;
}

.selection-marquee {
    position: absolute;
    border: 1px solid rgba(72, 255, 237, 0.3);
    background-color: rgba(72, 255, 237, 0.05);
    z-index: 100;
    pointer-events: none;
    box-shadow: 0 0 5px rgba(72, 255, 237, 0.1);
}

.vfd-display {
    position: absolute;
    bottom: 25px; 
    left: 50%;
    transform: translateX(-50%);
    background-color: #050908;
    border: 1px solid #1a1a1a;
}

.vfd-label-box {
    position: fixed;
    bottom: 1.8rem;
    left: calc(50% - 208px);
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0;
    pointer-events: none;
    z-index: 10001;
}

.bezel-logo {
    height: 34px;
    width: 37px;
    filter: invert(1) brightness(0.4) contrast(1.2) opacity(0.4);
    mix-blend-mode: normal;
}

.vfd-label-text { transform: translateY(1px); display: flex; flex-direction: column; }

.vfd-label-line1, .vfd-label-line2 {
    opacity: 0.8;
    font-family: 'Microgramma', 'Courier New', monospace;
    font-size: 0.61rem;
    color: #444;
    letter-spacing: 1px;
    text-align: left;
    background-clip: text;
    -webkit-background-clip: text;
}
.vfd-label-line2 { font-size: 0.6rem; margin-top: 1px; }

/* Mobile */
@media screen and (max-width: 900px) {
    .crt-wrapper { padding: 0; background: #000; }
    .crt-wrapper::after, .bezel-sticker, .bezel-reflection, .vfd-label-box, .crt-controls, .vfd-display { display: none !important; }
    .crt-screen { border-radius: 0; border: none; box-shadow: none; width: 100vw; height: 100vh; }
    .app-container { width: 100%; height: 100%; }
}
</style>
