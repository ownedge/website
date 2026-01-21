<script setup>
import { ref, computed, onUnmounted } from 'vue';

const props = defineProps({
  volume: { type: Number, required: true },
  brightness: { type: Number, required: true }, // 0.5 - 1.5
  contrast: { type: Number, required: true },   // 0.5 - 1.5
  isCapsLock: { type: Boolean, default: false },
  isHddActive: { type: Boolean, default: false },
  isTurbo: { type: Boolean, default: true },
  powerLed: { type: Boolean, default: true }
});

const emit = defineEmits([
  'update:volume', 
  'update:brightness', 
  'update:contrast',
  'knob-start', // { type, value }
  'knob-change', // { type, value }
  'knob-end'
]);

// Generic Knob State
const activeKnob = ref(null); // 'vol', 'brt', 'con'
const startY = ref(0);
const startValue = ref(0);

const handleKnobDown = (e, type) => {
    activeKnob.value = type;
    startY.value = e.clientY;
    
    // Capture distinct start values
    if (type === 'vol') startValue.value = props.volume;
    if (type === 'brt') startValue.value = props.brightness;
    if (type === 'con') startValue.value = props.contrast;

    // Notify Parent (for VFD)
    emit('knob-start', { type, value: startValue.value });

    document.addEventListener('mousemove', handleKnobMove);
    document.addEventListener('mouseup', handleKnobUp);
    e.preventDefault(); 
    e.stopPropagation(); // Stop propagation to prevent selection marquee in App.vue
};

const handleKnobMove = (e) => {
    if (!activeKnob.value) return;
    const deltaY = startY.value - e.clientY; 
    const sensitivity = 0.005; 
    let newVal = startValue.value + deltaY * sensitivity;

    if (activeKnob.value === 'vol') {
        newVal = Math.max(0, Math.min(1, newVal));
        emit('update:volume', newVal);
    } 
    else if (activeKnob.value === 'brt') {
        newVal = Math.max(0.5, Math.min(1.5, newVal)); // 50% to 150%
        emit('update:brightness', newVal);
    }
    else if (activeKnob.value === 'con') {
        newVal = Math.max(0.5, Math.min(1.5, newVal)); // 50% to 150%
        emit('update:contrast', newVal);
    }
    
    // Notify Parent for VFD updates
    emit('knob-change', { type: activeKnob.value, value: newVal });
};

const handleKnobUp = () => {
    activeKnob.value = null;
    emit('knob-end');
    
    document.removeEventListener('mousemove', handleKnobMove);
    document.removeEventListener('mouseup', handleKnobUp);
};

onUnmounted(() => {
    document.removeEventListener('mousemove', handleKnobMove);
    document.removeEventListener('mouseup', handleKnobUp);
});

// Computed Styles
const getKnobRotation = (val, min, max) => {
    // Normalize to 0-1
    const norm = (val - min) / (max - min);
    // Map to -135deg to +135deg
    return `rotate(${(norm * 270) - 135}deg)`;
};

const volKnobStyle = computed(() => ({ transform: getKnobRotation(props.volume, 0, 1) }));
const brtKnobStyle = computed(() => ({ transform: getKnobRotation(props.brightness, 0.5, 1.5) }));
const conKnobStyle = computed(() => ({ transform: getKnobRotation(props.contrast, 0.5, 1.5) }));

// Dynamic Light Spill Calculation
const calculateSpill = (val, min, max) => {
    // Normalize 0-1
    const norm = (val - min) / (max - min);
    
    // 0deg (Top, norm 0.5) -> Furthest (0 opacity)
    // -135deg (Bottom Left, norm 0) -> Closest (1 opacity)
    // +135deg (Bottom Right, norm 1) -> Closest (1 opacity)
    
    const prominence = Math.abs(norm - 0.5) * 2; // 0 (top) to 1 (bottom)
    
    // Linear is usually better for "gradual" feel than square
    // But let's boost it slightly so it starts glowing "earlier" in the turn
    const opacityValues = Math.pow(prominence, 1.5); 
    
    // Convert to Percentage for CSS color-mix
    return `${(opacityValues * 100).toFixed(0)}%`;
};

</script>

<template>
    <div class="crt-controls">
         <!-- Fixed Status LEDs -->
        <div class="led-panel">
            <div class="led-group" style="--led-color: #33ff33;">
                <div class="led active-caps" :class="{ active: isCapsLock }"></div>
                <span class="led-label">CAPS</span>
            </div>
            <div class="led-group" style="--led-color: #ffaa00;">
                <div class="led hdd-led" :class="{ active: isHddActive }"></div>
                <span class="led-label">DISK</span>
            </div>
            <div class="led-group" style="--led-color: #ffff00;">
                <div class="led turbo-led" :class="{ active: isTurbo }"></div>
                <span class="led-label">TURBO</span>
            </div>
        </div>

        <!-- Power Panel (Knobs + Power LED) -->
        <div class="power-panel">
            <!-- Volume Knob -->
            <div class="volume-control" @mousedown="(e) => handleKnobDown(e, 'vol')">
                 <div class="knob-container">
                     <div class="knob-ring"></div>
                     <div class="knob-arrows">
                         <span class="arrow-up">▲</span>
                         <span class="arrow-down">▼</span>
                     </div>
                     <div class="knob" :style="volKnobStyle">
                         <div class="knob-marker"></div>
                     </div>
                 </div>
                  <span class="led-label" 
                      :style="{ 
                          '--spill-opacity': calculateSpill(volume, 0, 1),
                          '--led-color': '#ff0000'
                      }"
                  >VOLUME</span>
            </div>

            <!-- Brightness Knob -->
            <div class="volume-control" @mousedown="(e) => handleKnobDown(e, 'brt')">
                 <div class="knob-container">
                     <div class="knob-ring"></div>
                     <div class="knob-arrows">
                         <span class="arrow-up">▲</span>
                         <span class="arrow-down">▼</span>
                     </div>
                     <div class="knob" :style="brtKnobStyle">
                         <div class="knob-marker"></div>
                     </div>
                 </div>
                  <span class="led-label" 
                      :style="{ 
                          '--spill-opacity': calculateSpill(brightness, 0.5, 1.5),
                          '--led-color': '#ff0000'
                      }"
                  >BRIGHT</span>
            </div>

            <!-- Contrast Knob -->
            <div class="volume-control" @mousedown="(e) => handleKnobDown(e, 'con')">
                 <div class="knob-container">
                     <div class="knob-ring"></div>
                     <div class="knob-arrows">
                         <span class="arrow-up">▲</span>
                         <span class="arrow-down">▼</span>
                     </div>
                     <div class="knob" :style="conKnobStyle">
                         <div class="knob-marker"></div>
                     </div>
                  </div>
                  <span class="led-label"
                      :style="{ 
                          '--spill-opacity': calculateSpill(contrast, 0.5, 1.5),
                          '--led-color': '#ff0000'
                      }"
                  >CONTRST</span>
            </div>

            <div class="led-group" style="--led-color: #33ff33;">
                <div class="led power-led" :class="{ active: powerLed }"></div>
                <span class="led-label">POWER</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* LED Status Panel */
.led-panel {
    position: fixed;
    bottom: 1.0rem; /* Sit in the bottom bezel padding */
    left: 4rem;
    display: flex;
    gap: 1.5rem;
    pointer-events: none;
    z-index: 10000;
}

.power-panel {
    position: fixed;
    bottom:1.0rem; /* Sit in the bottom bezel padding */
    right: 4rem;
    display: flex;
    align-items: flex-end; /* Align baselines of labels */
    gap: 1.5rem;
    pointer-events: none;
    z-index: 10000;
}

.led-group {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.8rem;
    width: 60px; /* Enforce overlapping width to keep centers equidistant */
}

/* Standardized LED Lighting */
.led-group {
    /* Default Green if not set */
    --led-color: #33ff33; 
    --led-off: #1a1a1a;
}

.knob-container, .volume-control {
    --led-color: #ff0000;
}

/* Base LED Shape & Off State */
.led {
    width: 20px;
    height: 6px;
    border: 0.1px solid #161616;
    /* Mix a tiny bit of the color into the dark base for realism */
    background-color: color-mix(in srgb, var(--led-color), #000 90%);
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.8);
    transition: all 0.1s ease-out;

    /* Dot Matrix Texture - Reduced for the thin shape */
    background-image: 
        radial-gradient(circle at center, rgba(255,255,255,0.05) 0.5px, transparent 1px);
    background-size: 2px 2px;
}

/* Active State (Unified) */
.led.active, .power-led.active {
    /* Core Hot Spot + Bloom (Dimmed) */
    background: radial-gradient(
        circle at 50% 50%, 
        color-mix(in srgb, var(--led-color), #fff 70%) 0%, 
        var(--led-color) 70%,
        color-mix(in srgb, var(--led-color), #000 60%) 100%
    );

        
    border-color: color-mix(in srgb, var(--led-color), #000000 100%);
    z-index: 10002;
    filter: brightness(0.9); /* Reduced brightness for a modern feel */
    animation: led-pulse 4s ease-in-out infinite;
}

/* Label Spill (Standardized) */
.led-label {
    font-family: 'Microgramma', 'Courier New', monospace;
    font-size: 0.6rem;
    color: #444; 
    letter-spacing: 1px;
    transition: all 0.25s ease;
    background-color: #444;
    background-clip: text;
    -webkit-background-clip: text;
    color: #444; 
}

/* Active Label Spill */
.led-group:has(.led.active) .led-label,
.power-panel .led-group:has(.power-led.active) .led-label {
    --spill-opacity: 45%; /* Reduce max intensity */
}

/* Common Spill Logic */
.led-group:has(.led.active) .led-label,
.power-panel .led-group:has(.power-led.active) .led-label,
.volume-control .led-label {
    background-image: radial-gradient(
        circle at 50% -15px, 
        /* Mix led-color with some dark base opacity to reduce "neon" look */
        color-mix(in srgb, var(--led-color), rgba(68,68,68,0.5) calc(100% - var(--spill-opacity, 0%))) 0%, 
        #444 65%
    );
    color: transparent;
    -webkit-text-fill-color: transparent;
    
    /* Subtle glow matching color */
    text-shadow: 0 -1px 2px color-mix(in srgb, var(--led-color), transparent calc(100% - var(--spill-opacity, 0%) * 0.15));
}

/* Volume Knob */
.volume-control {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.3rem;
    width: 60px; /* Fixed Layout Width */
    /* Remove padding/margin hacks - 60px is sufficient tap target */
    padding: 0; 
    margin: 0;
    position: relative;
    z-index: 10005; /* Ensure it's above other things */
    pointer-events: auto; /* Capture hover in padded area */
}

.knob-container {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.knob-arrows {
    position: absolute;
    left: -15px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    flex-direction: column;
    gap: 12px;
    opacity: 0;
    transition: opacity 0.6s;
    pointer-events: none;
}

.arrow-up, .arrow-down {
    font-size: 8px;
    color: #424242;
}

.volume-control:hover .knob-arrows {
    opacity: 1;
}

.volume-control:hover .knob {
    cursor: grab;
}

.volume-control:active .knob {
    cursor: grabbing;
}

.knob {
    width: 24px;
    height: 24px;
    background: radial-gradient(circle at 30% 30%, #333, #111);
    border-radius: 50%;
    border: 1px solid #000;
    box-shadow: 
        0 2px 5px rgba(0,0,0,0.8),
        inset 0 1px 1px rgba(255,255,255,0.1);
    position: relative;
    cursor: ns-resize; /* Indicate drag */
    pointer-events: auto; /* Enable interaction */
}

.knob-marker {
    position: absolute;
    top: 3px;
    left: 50%;
    transform: translateX(-50%);
    width: 5px;
    height: 5px;
    border-radius: 50%;
    
    /* Red Bloom Effect (Dimmed) */
    background: radial-gradient(circle at 40% 40%, #ffcccc 0%, #cc0000 40%, #880000 80%);
    box-shadow: 
        0 0 2px 0px #ffcccc,
        0 0 4px 1px #aa0000,
        0 0 6px 1px #660000;
        
    animation: led-pulse 4s ease-in-out infinite; /* Slower pulse for consistent warmth */
}

@keyframes led-pulse {
    0%, 100% {
        filter: brightness(0.8);
    }
    50% {
        filter: brightness(0.9);
    }
}
</style>
