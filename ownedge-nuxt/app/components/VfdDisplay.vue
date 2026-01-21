<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import SoundManager from '~/utils/sfx/SoundManager';
import funnySunPath from '../assets/funny_sun.png';
import funnyEveningPath from '../assets/funny_evening.png';
import funnyNightPath from '../assets/funny_night.png';

const funnySun = ref(null);
const funnyEvening = ref(null);
const funnyNight = ref(null);

if (import.meta.client) {
  const iSun = new Image(); iSun.src = funnySunPath;
  funnySun.value = iSun;
  
  const iEve = new Image(); iEve.src = funnyEveningPath;
  funnyEvening.value = iEve;
  
  const iNig = new Image(); iNig.src = funnyNightPath;
  funnyNight.value = iNig;
} 

const props = defineProps({
  mode: {
    type: String,
    default: 'spectrum' // 'off', 'logo', 'spectrum', 'knob'
  },
  knobInfo: {
    type: Object,
    default: () => ({ label: '', value: '' })
  },
  bootState: {
    type: String,
    default: 'complete' // 'loading', 'ready', 'complete'
  },
  bootProgress: {
    type: Number,
    default: 0
  },
  scanlineColor: {
    type: String,
    default: 'rgba(0,0,0,0.9)'
  },
  statusText: {
    type: String,
    default: 'wait'
  }
});

const vfdCanvas = ref(null);
const readyTimestamp = ref(0);
let animationFrameId = null;

// Egg Animation State
const eggState = ref('idle'); // 'idle', 'scroll', 'morph', 'explode'
const eggProgress = ref(0);
const eggType = ref({ text: '', icon: '', period: '' });
const particles = ref([]);

const getGreeting = () => {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 18) return { words: ["WONDERFUL", "DAY!"], icon: funnySun.value, period: 'day' };
    if (hour >= 18 && hour < 21) return { words: ["GOOD", "EVENING!"], icon: funnyEvening.value, period: 'evening' };
    return { words: ["ENJOY", "THE", "NIGHT!"], icon: funnyNight.value, period: 'night' };
};

let lastEggCheck = 0;

watch(() => props.bootState, (newState) => {
    if (newState === 'ready') {
        readyTimestamp.value = Date.now();
    }
});

watch(() => props.mode, (newMode) => {
    // Always restart loop on mode change to ensure freshness (if spectrum)
    if (newMode === 'spectrum') {
        startSpectrumAnalyzer();
    }
});

const startSpectrumAnalyzer = () => {
    if (animationFrameId) cancelAnimationFrame(animationFrameId);

    const draw = () => {
        // Handle Boot States Override (Highest Priority)
        // If bootState is NOT complete, we hijacked the display for the loading bar
        if (props.bootState !== 'complete') {
             if (!vfdCanvas.value) {
                 animationFrameId = requestAnimationFrame(draw);
                 return;
             }
             const canvas = vfdCanvas.value;
             const ctx = canvas.getContext('2d');
             
             // Ensure size
             if (canvas.width !== 185) { canvas.width = 185; canvas.height = 36; }
             
             ctx.clearRect(0, 0, canvas.width, canvas.height);
             ctx.fillStyle = '#40e0d0';

             if (props.bootState === 'loading') {
                 // Explosive Counting Animation
                 const progress = Math.min(props.bootProgress, 100);
                 const targetNumber = Math.floor(progress);
                 
                 // Rapid counting with explosion effect
                 const countSpeed = 200; // ms per number change
                 const currentCount = Math.min(targetNumber, Math.floor(Date.now() / countSpeed) % 101);
                 
                 // Calculate scale based on number change (explosion effect)
                 const timeSinceChange = (Date.now() % countSpeed) / countSpeed;
                 const explosionScale = 1 + (1 - timeSinceChange) * 0.5; // Starts big, shrinks to normal
                 
                 // Size increases as we get closer to 100
                 const progressScale = 1 + (currentCount / 100) * 0.8;
                 const finalScale = explosionScale * progressScale;
                 
                 // Font size grows with progress
                 const baseFontSize = 28;
                 const fontSize = baseFontSize * finalScale;
                 
                 ctx.font = `900 ${fontSize}px Microgramma, 'Arial Black', sans-serif`;
                 ctx.textAlign = 'center';
                 ctx.textBaseline = 'middle';
                 
                 // Keep color constant
                 ctx.fillStyle = '#40e0d0';
                 
                 // Add glow effect that pulses
                 const glowIntensity = (1 - timeSinceChange) * 15;
                 ctx.shadowColor = `rgba(64, 224, 208, ${0.8 * (1 - timeSinceChange)})`;
                 ctx.shadowBlur = glowIntensity;
                 
                 // Draw the number
                 ctx.fillText(String(currentCount).padStart(2, '0'), canvas.width / 2, canvas.height / 2);
                 
                 // Reset shadow
                 ctx.shadowBlur = 0;
                 
             } else if (props.bootState === 'connecting') {
                 ctx.fillStyle = '#40e0d0';
                 ctx.font = "bold 16px 'Microgramma'";
                 ctx.textAlign = 'center';
                 ctx.textBaseline = 'middle';
                 
                 // Add Glow for Lit State
                 ctx.shadowColor = "rgba(64, 224, 208, 0.6)";
                 ctx.shadowBlur = 8;
                 ctx.fillText(props.statusText, canvas.width / 2, canvas.height / 2);
                 ctx.shadowBlur = 0; 

             } else if (props.bootState === 'ready') {
                 // 1. Calculate Geometry
                 const width = canvas.width * 0.95;
                 const x = (canvas.width - width) / 2;
                 const height = 32; 
                 const y = (canvas.height - height) / 2;
                 
                 const now = Date.now();
                 const elapsed = now - readyTimestamp.value;
                 const blinkDuration = 20; // ms per phase
                 const blinkCount = 2;
                 const totalBlinkTime = blinkCount * 2 * blinkDuration;
                 
                 // Phase 1: Inverted Blinking (Block with Cutout)
                 if (elapsed < totalBlinkTime) {
                     // Draw Solid Block
                     ctx.fillStyle = '#40e0d0';
                     ctx.fillRect(x, y, width, height);
                     
                     const phase = Math.floor(elapsed / blinkDuration);
                     const showCutout = (phase % 2 === 0); // ON phase has hole
                     
                     if (showCutout) {
                         ctx.globalCompositeOperation = 'destination-out';
                         ctx.font = "bold 28px 'Microgramma'";
                         ctx.textAlign = 'center';
                         ctx.textBaseline = 'middle';
                         ctx.fillText(" ENTER ↵", canvas.width / 2, canvas.height / 2);
                         ctx.globalCompositeOperation = 'source-over';
                     }
                 } 
                 // Phase 2: Lit Text (Normal Text, No Block)
                 else {
                     ctx.fillStyle = '#40e0d0';
                     ctx.font = "bold 28px 'Microgramma'";
                     ctx.textAlign = 'center';
                     ctx.textBaseline = 'middle';
                     
                     // Add Glow for Lit State
                     ctx.shadowColor = "rgba(64, 224, 208, 0.6)";
                     ctx.shadowBlur = 8;
                     ctx.fillText(" ENTER ↵", canvas.width / 2, canvas.height / 2);
                     ctx.shadowBlur = 0; 
                 }
             }
             
             animationFrameId = requestAnimationFrame(draw);
             return;
        }


        // Normal Operation: Spectrum Analyzer
        if (props.mode !== 'spectrum') return;
        
        if (!vfdCanvas.value) {
             animationFrameId = requestAnimationFrame(draw);
             return;
        }

        const canvas = vfdCanvas.value;
        const ctx = canvas.getContext('2d');
        
        if (canvas.width !== 185) {
            canvas.width = 185;
            canvas.height = 36;
        }

        // --- Easter Egg Handling ---
        const now = Date.now();
        if (eggState.value === 'idle' && now - lastEggCheck > 45000) {
            lastEggCheck = now;
            if (Math.random() < 0.2) {
                eggType.value = getGreeting();
                eggState.value = 'scroll';
                eggProgress.value = 0;
            }
        }

        if (eggState.value !== 'idle') {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#40e0d0';
            ctx.font = "900 24px Microgramma, sans-serif";
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            if (eggState.value === 'scroll') {
                eggProgress.value += 0.007; 
                const words = eggType.value.words || [];
                const wordIndex = Math.floor(eggProgress.value * words.length);
                
                if (wordIndex < words.length) {
                    const word = words[wordIndex];
                    const wordProgress = (eggProgress.value * words.length) % 1;
                    const scale = 0.3 + Math.pow(wordProgress, 0.5) * 1.5;
                    let opacity = 1;
                    if (wordProgress < 0.2) opacity = wordProgress / 0.2;
                    else if (wordProgress > 0.8) opacity = 1 - (wordProgress - 0.8) / 0.2;

                    ctx.save();
                    ctx.globalAlpha = opacity;
                    ctx.translate(canvas.width / 2, canvas.height / 2);
                    ctx.scale(scale, scale);
                    ctx.font = "900 20px Microgramma, sans-serif";
                    ctx.fillText(word, 0, 0);
                    ctx.restore();
                }
                
                if (eggProgress.value >= 1) {
                    eggState.value = 'morph';
                    eggProgress.value = 0;
                }
            } else if (eggState.value === 'morph') {
                eggProgress.value += 0.006; 
                
                const img = eggType.value.icon;
                if (img && img.complete) {
                    const inDuration = 0.4;
                    const isDownUp = eggType.value.period === 'day' || eggType.value.period === 'night';
                    const imgSize = 110;
                    const targetY = isDownUp ? (canvas.height - imgSize) / 2 : (canvas.height - imgSize) / 2;
                    const targetX = (canvas.width - imgSize) / 2;
                    
                    let yOffset = targetY;
                    if (eggProgress.value < inDuration) {
                        const progress = eggProgress.value / inDuration;
                        if (isDownUp) {
                            const startY = canvas.height;
                            yOffset = startY + (targetY - startY) * progress;
                        } else {
                            const startY = -imgSize;
                            yOffset = startY + (targetY - startY) * progress;
                        }
                    }

                    ctx.save();
                    
                    // Procedural Animation Logic
                    const time = Date.now() / 1000;
                    let rotate = 0;
                    let scaleBoost = 1.0;
                    let extraX = 0;
                    let extraY = 0;

                    if (eggType.value.period === 'day') {
                        // Sunny-Dude: Energetic Wobble & Breathe
                        rotate = Math.sin(time * 10) * 0.1;
                        scaleBoost = 1 + Math.sin(time * 5) * 0.05;
                    } else if (eggType.value.period === 'evening') {
                        // Sleepy Sun: Slow Nodding
                        rotate = Math.sin(time * 2) * 0.05;
                    } else if (eggType.value.period === 'night') {
                        // Sneaker Moon: Excited Jiggle Jump
                        extraY = Math.abs(Math.sin(time * 12)) * -5;
                        rotate = Math.sin(time * 15) * 0.08;
                    }

                    // Apply transformation relative to center of icon
                    ctx.translate(targetX + imgSize/2 + extraX, yOffset + imgSize/2 + extraY);
                    ctx.rotate(rotate);
                    ctx.scale(scaleBoost, scaleBoost);
                    ctx.translate(-(imgSize/2), -(imgSize/2));

                    // 1. Draw the icon as a mask
                    ctx.drawImage(img, 0, 0, imgSize, imgSize);
                    
                    // 2. Color it teal using 'source-in'
                    ctx.globalCompositeOperation = 'source-in';
                    ctx.fillStyle = '#40e0d0';
                    ctx.fillRect(0, 0, imgSize, imgSize);
                    
                    ctx.restore(); 
                }
                
                if (eggProgress.value >= 1) {
                    eggState.value = 'explode';
                    particles.value = [];
                    for (let i = 0; i < 50; i++) {
                        const angle = Math.random() * Math.PI * 2;
                        const speed = 0.5 + Math.random() * 5;
                        particles.value.push({
                            x: canvas.width / 2,
                            y: canvas.height / 2,
                            vx: Math.cos(angle) * speed,
                            vy: Math.sin(angle) * speed,
                            life: 1.0,
                            size: 0.5 + Math.random() * 2
                        });
                    }
                }
            } else if (eggState.value === 'explode') {
                let active = false;
                particles.value.forEach(p => {
                    p.x += p.vx;
                    p.y += p.vy;
                    p.life -= 0.02;
                    if (p.life > 0) {
                        active = true;
                        ctx.globalAlpha = p.life;
                        ctx.fillRect(p.x, p.y, p.size, p.size);
                    }
                });
                ctx.globalAlpha = 1.0;
                if (!active) {
                    eggState.value = 'idle';
                    lastEggCheck = Date.now();
                }
            }

            animationFrameId = requestAnimationFrame(draw);
            return;
        }

        const data = SoundManager.getAudioData();
        
        if (!data) {
             animationFrameId = requestAnimationFrame(draw);
             return;
        }

        // Clear
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Dot Matrix Config
        // 185px width, 36px height at 100% zoom (approx)
        const dotSize = 0.5;
        const gap = 0; 
        const paddingX = 4;
        const paddingY = 2;
        
        const step = dotSize + gap;
        // Calculate columns based on width minus padding
        const cols = Math.floor((canvas.width - (paddingX * 2)) / step);
        const rows = Math.floor((canvas.height - (paddingY * 2)) / step);
        
        ctx.fillStyle = '#48ffed'; // Teal VFD color
        ctx.shadowColor = '#40e0d0';
        ctx.shadowBlur = 1;

        // Draw Visualization
        for (let i = 0; i < cols; i++) {
            const binIndex = Math.floor((i / cols) * data.length * 0.5); 
            const value = data[binIndex] || 0; 
            
            const heightPercent = value / 255;
            const barHeight = heightPercent * (canvas.height - (paddingY * 2));
            
            if (barHeight > 0) {
                const x = paddingX + (i * step);
                const y = canvas.height - paddingY - barHeight;
                
                ctx.globalAlpha = 0.8 + (value / 1200);
                ctx.fillRect(x, y, dotSize, barHeight);
            }
        }
        
        ctx.shadowBlur = 0; // Reset for other frames
        
        animationFrameId = requestAnimationFrame(draw);
    };
    
    draw();
};

// Lifecycle
onMounted(() => {
    startSpectrumAnalyzer();
});

onUnmounted(() => {
    if (animationFrameId) cancelAnimationFrame(animationFrameId);
});

// Reactivity
watch(() => props.mode, (newMode) => {
    if (newMode === 'spectrum') {
        startSpectrumAnalyzer();
    } else {
        if (animationFrameId) cancelAnimationFrame(animationFrameId);
    }
});

// Also watch bootState - if it becomes spectrum-relevant (e.g., switches out of boot), restart
// (Note: The specific watch above handles the 'ready' timestamp logic, this global one ensures loop continuity)
// Merged into the specific watch above to avoid duplication, or can keep generic logic here.
// Actually, let's remove the duplicate watch to be clean.

</script>

<template>
    <div class="vfd-display">
       <div class="vfd-overlay"></div> <!-- Dot Matrix Grid Mask -->
       
       <!-- WELCOME Animation -->
       <Transition name="vfd-anim" mode="out-in">
           <div v-if="mode === 'knob'" class="vfd-info-container">
               <span class="vfd-label">{{ knobInfo.label }}</span>
               <span class="vfd-value">{{ knobInfo.value }}</span>
           </div>

           <!-- Pinball Logic Animation -->
           <div v-else-if="mode === 'logo'" class="pinball-container">
                <div class="pinball-ball"></div>
                <div class="pinball-text">WELCOME</div>
           </div>
    
           <!-- Spectrum Analyzer OR Loading Bar -->
           <canvas 
            v-else
            ref="vfdCanvas" 
            class="vfd-canvas"
            ></canvas>
       </Transition>
    </div>
</template>

<style scoped>
/* VFD Display Container */
.vfd-display {
    background-color: #000;
    margin: 0; 
    border-bottom: 1px solid #222;
    padding: 4px; 
    border-radius: 4px; /* Slightly more rounded */
    box-shadow: 
        inset 0 2px 10px rgba(0,0,0,1), /* Stronger top inner shadow */
        inset 0 0 5px rgba(0,0,0,0.8),
        0 1px 0 rgba(255, 255, 255, 0.05); /* Subtle lip */
    z-index: 10001;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 185px; /* Wider (120%) */
    height: 44px;
    overflow: hidden; 
    position: relative;
    
    /* Simulate slight curve/recess */
    background-image: linear-gradient(to bottom, #000 0%, #080a08 20%, #080a08 80%, #000 100%);
}

/* Glass & Dot Grid Effect */
.vfd-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  /* Create a 'mask' of black with transparent holes */
  background: radial-gradient(
    circle,
    transparent 10%,
    v-bind(scanlineColor) 95%
  );
  background-size: 2px 2px; /* Dot density */
  pointer-events: none;
  z-index: 50;
  opacity: 0.9;
}

/* Pinball Animation Styles */
.pinball-container {
    width: 100%;
    height: 100%;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.pinball-ball {
    position: absolute;
    top: 50%;
    left: -10px;
    width: 6px;
    height: 6px;
    background-color: #40e0d0;
    transform: translateY(-50%);
    box-shadow: 0 0 5px #40e0d0;
    animation: ball-roll 0.4s linear forwards;
}

.pinball-text {
    font-family: 'Microgramma';
    color: #40e0d0;
    font-size: 1.6rem;
    font-weight: 900;
    letter-spacing: 1px;
    opacity: 0; 
    animation: text-jackpot 2s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    animation-delay: 0.38s; /* Wait for ball impact */
}

@keyframes ball-roll {
    0% { left: -10px; }
    90% { left: 50%; opacity: 1; }
    100% { left: 50%; opacity: 0; } /* Disappear on impact */
}

@keyframes text-jackpot {
    0% { 
        transform: scale(0);
        opacity: 0;
        text-shadow: 0 0 20px #fff;
    }
    1% {
        opacity: 1;
        color: #fff; /* Flash white */
    }
    5% {
        transform: scale(1.3);
        color: #40e0d0;
    }
    10% { transform: scale(0.9); }
    15% { transform: scale(1.0); }
    
    /* Blink effects */
    20% { color: #40e0d0; text-shadow: 0 0 15px #40e0d0; }
    25% { color: #40e0d0; text-shadow: 0 0 8px #40e0d0; }
    30% { color: #40e0d0; text-shadow: 0 0 15px #40e0d0; }
    35% { color: #40e0d0; text-shadow: 0 0 8px #40e0d0; }
    
    100% { 
        transform: scale(1); 
        opacity: 1; 
        color: #40e0d0; 
        text-shadow: 0 0 8px #40e0d0;
    }
}

/* Special Exit (Warp Out) */
.vfd-anim-leave-active.pinball-container {
    /* Dummy animation to force Vue to wait 0.5s while keeping container visible */
    animation: hold-visible 0.5s forwards !important;
    opacity: 1 !important; 
    transform: none !important;
}

@keyframes hold-visible { from { opacity: 1; } to { opacity: 1; } }

.vfd-anim-leave-active .pinball-text {
    animation: text-warp-out 0.5s forwards !important;
}

@keyframes text-warp-out {
    0% { 
        transform: scaleX(1) translateX(0); 
        opacity: 0.9;
        filter: blur(0px);
    }
    10% {
        transform: scaleX(1.5) translateX(5px);
        opacity: 1;
        color: #fff; /* Flash white */
        filter: blur(1px);
    }
    40% {
        transform: scaleX(4) translateX(20px); /* Stretch */
        opacity: 0.5;
        filter: blur(2px);
        letter-spacing: 20px; /* Explode letters */
    }
    100% { 
        transform: scaleX(10) translateX(50px); 
        opacity: 0;
        filter: blur(6px);
        letter-spacing: 50px; 
    }
}

.vfd-anim-enter-active,
.vfd-anim-leave-active {
  transition: all 0.35s cubic-bezier(0.19, 1, 0.22, 1);
}

/* Specialized Exit for Knob UI */
.vfd-anim-leave-active.vfd-info-container {
    animation: knob-warp-out 0.4s forwards;
    transition: none !important;
}

@keyframes knob-warp-out {
    0% { 
        transform: scaleX(1) translateY(0); 
        opacity: 1;
        filter: blur(0px);
    }
    15% {
        transform: scaleX(1.4) translateY(-2px);
        opacity: 1;
        filter: blur(1px);
    }
    100% { 
        transform: scaleX(6) translateY(-15px); 
        opacity: 0;
        filter: blur(8px);
    }
}

.vfd-anim-enter-from {
  opacity: 0;
  transform: translateY(-100%);
}

.vfd-anim-enter-from.vfd-canvas {
  transform: translateY(100%);
}

.vfd-anim-leave-to {
  opacity: 0;
  transform: translateY(100%);
}

.vfd-info-container {
    width: 100%;
    height: 100%;
    position: relative; 
    display: flex;
    align-items: center; 
    justify-content: space-between; /* Spread items */
    padding: 0 12px; /* Balanced padding */
}

.vfd-label {
    /* position: absolute; Removed for flex centering */
    /* top: 2px; */
    /* left: 8px; */
    font-family: 'Microgramma', monospace;
    color: #40e0d0;
    font-size: 0.55rem; /* Tiny label */
    letter-spacing: 1px;
    font-weight: bold;
    text-shadow: 0 0 5px #40e0d0;
    opacity: 0.8;
}

.vfd-value {
    font-family: 'Microgramma', monospace; 
    color: #48ffed;
    font-size: 1.8rem; /* Large Value */
    letter-spacing: 2px;
    font-weight: bold;
    text-shadow: 0 0 8px #40e0d0;
    text-shadow: 0 0 8px #40e0d0;
    line-height: 1;
    margin-top: 4px; /* Slight optical adjustment */
}
.vfd-canvas {
    filter: drop-shadow(0 0 1px rgba(67, 255, 236, 0.1));
}
</style>
