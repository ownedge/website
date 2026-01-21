<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import SoundManager from '~/utils/sfx/SoundManager';


const props = defineProps({
  screenRect: Object,
  reflectionOnly: Boolean
});
const canvasRef = ref(null);
const hasStarted = ref(false);
const dataFadeProgress = ref(0); // 0 to 1 for smooth fade-in
let animationFrameId = null;

// Cache for channel strings to avoid re-fetching/decoding WASM every frame
const rowCache = new Map();
const getCachedRow = (pattern, row) => {
    const key = `${pattern}:${row}`;
    if (rowCache.has(key)) return rowCache.get(key);
    
    // Not in cache, fetch and store
    // Note: This assumes pattern data is static (music doesn't change at runtime)
    const channels = SoundManager.getPatternRowData(pattern, row) || [];
    const formatted = (channels.length > 0 ? channels : ["???", "???", "???", "???"])
        .map(c => c ? c.replace(/\.\.\./g, '...') : '...');
    
    // Limit cache size to avoid memory leaks over long sessions
    if (rowCache.size > 2000) rowCache.clear();
    
    rowCache.set(key, formatted);
    return formatted;
};

// Pre-calc styles
const MAIN_COLOR = 'rgba(33, 241, 235)';
const FADE_STYLES = Array.from({ length: 11 }, (_, i) => {
    // offset 0 is special, handled separately
    if (i === 0) return `rgba(33, 241, 235, 1)`;
    const opacity = 0.7 - (i * 0.15);
    return `rgba(33, 241, 235, ${Math.max(0, opacity)})`;
});


const draw = () => {
    const canvas = canvasRef.value;
    if (!canvas) {
        animationFrameId = requestAnimationFrame(draw);
        return;
    }
    
    // Resize only on dedicated resize event or if seriously different
    const targetW = window.innerWidth;
    const targetH = window.innerHeight;
    if (canvas.width !== targetW || canvas.height !== targetH) {
        canvas.width = targetW;
        canvas.height = targetH;
    }

    const ctx = canvas.getContext('2d', { alpha: true });
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Base Style
    ctx.textAlign = 'center';
    ctx.textBaseline = 'top';


    // --- RENDER CONSTANTS ---
    const ROWS_TO_SHOW = 10;
    const lineHeight = 16;
    
    // Calculate Position Per Frame to handle prop updates
    let startX = canvas.width / 2;
    let startY = 6;
    
    // Y-Logic:
    if (props.reflectionOnly && props.screenRect) {
        startY = props.screenRect.top;
        // X-Logic: Match the inner data's visual position.
        startX = props.screenRect.left + (canvas.width / 2);
    }

    // --- DATA FETCH ---
    let currentPos = SoundManager.getTrackerPosition();
    let isWaitingForData = false;

    if (!currentPos) {
        // Visualizer not loaded yet
        isWaitingForData = true;
    } else {
        // Data found - trigger fade-in
        if (!hasStarted.value) {
            hasStarted.value = true;
        }
    }
    
    // Animate fade-in when data starts
    if (hasStarted.value && dataFadeProgress.value < 1) {
        dataFadeProgress.value += 0.02; // Fade over ~50 frames (about 1 second)
        if (dataFadeProgress.value > 1) dataFadeProgress.value = 1;
    }

    if (isWaitingForData) {
        animationFrameId = requestAnimationFrame(draw);
        return;
    }
    
    // Global Alpha for the whole visualizer fade-in
    ctx.globalAlpha = dataFadeProgress.value;

    // --- RENDER ---
    
    // 1. Draw Active Row (High Impact)
    // We treat offset 0 specially for glow effects
    if (!props.reflectionOnly) {
        const rowChannels = getCachedRow(currentPos.pattern, currentPos.row);
        const channelStr = rowChannels.join('  ');
        const str = `${String(currentPos.row).padStart(2,'0')} | ${channelStr}`;
        
        ctx.font = 'bold 16px "Courier New", monospace';
        ctx.fillStyle = MAIN_COLOR;
        
        // Glow Effect
        ctx.shadowBlur = 8;
        ctx.shadowColor = MAIN_COLOR;
        
        ctx.fillText(str, startX, startY);
        
        // Reset Shadow for subsequent draws
        ctx.shadowBlur = 0;
        ctx.shadowColor = 'transparent';
    } else {
        // Reflection Mode: Active Row
        const rowChannels = getCachedRow(currentPos.pattern, currentPos.row);
        const str = `${String(currentPos.row).padStart(2,'0')} | ${rowChannels.join('  ')}`;
        
        ctx.font = 'bold 16px "Courier New", monospace';
        ctx.fillStyle = 'rgba(33, 241, 235, 0.5)';
        
        ctx.save();
        ctx.translate(0, startY);
        ctx.scale(1, -0.35); // Flip UP
        ctx.fillText(str, startX +0, -5); 
        ctx.restore();
    }
    
    // 2. Draw Future Rows
    // We skip offset 0 as it's already drawn
    // Reflection Only: Draw top row only (offset 0), so we skip the loop entirely if reflecting
    if (!props.reflectionOnly) {
        ctx.font = '16px "Courier New", monospace'; // Reset font for loop
        
        for (let offset = 1; offset <= ROWS_TO_SHOW; offset++) {
            let rPos = currentPos.row + offset;
            let pPos = currentPos.pattern;
            let rowsInPattern = currentPos.numRows || 64;

            // Simple Pattern Wrapping for visualization continuity
            // If we go past end of pattern, just show empty or "???" to indicate boundary
            let rowChannels = ["", "", "", ""]; 
            
            if (rPos < rowsInPattern) {
               rowChannels = getCachedRow(pPos, rPos);
            }
            
            // Optimization: Skip empty rows
            if (rowChannels[0] === "") continue;

            const y = startY + (offset * lineHeight);
            
            // Pre-calculated style
            ctx.fillStyle = FADE_STYLES[offset] || FADE_STYLES[10];
            
            const str = `${String(rPos).padStart(2,'0')} | ${rowChannels.join('  ')}`;
            ctx.fillText(str, startX, y);
        }
    }
    
    // Restore Alpha
    ctx.globalAlpha = 1.0;
    
    // EDGE FADE MASK
    // Proportional side margins to prevent abrupt cutoff on side edges
    const marginSide = canvas.width < 900 ? 5 : 65;
    
    // EDGE FADE MASK (Reflection Only)
    // "Hide reflected line from absolute 0 to 40 and -40 to full width"
    ctx.save();
    ctx.globalCompositeOperation = 'destination-in';
    ctx.fillStyle = '#000';
    if (props.reflectionOnly) {
        ctx.fillRect(65, 0, canvas.width - (marginSide * 2), canvas.height);
    } else {
        ctx.fillRect(15, 0, canvas.width - (marginSide * 2), canvas.height);
    }
    ctx.restore();
    
    animationFrameId = requestAnimationFrame(draw);
};

onMounted(() => {
    draw();
});

onUnmounted(() => {
    if (animationFrameId) cancelAnimationFrame(animationFrameId);
    rowCache.clear();
});
</script>

<template>
    <div :class="['tracker-overlay', { 'reflection-mode': reflectionOnly }]">
        <canvas ref="canvasRef"></canvas>
    </div>
</template>

<style scoped>
.tracker-overlay {
    position: absolute;
    top: 0; /* Align to top of screen */
    left: 0;
    width: 100%;
    height: 180px; /* Extended height to prevent cutoff of fade */
    pointer-events: none;
    z-index: 15; /* Behind content (20) but above generic BG/Grid (10) */
    overflow: hidden;
}

.tracker-overlay.reflection-mode {
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    opacity: 1.0; /* Lowered opacity */
    z-index: 100;
    filter: blur(3.2px);
}

canvas {
    display: block;
}
</style>
