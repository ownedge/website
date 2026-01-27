<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';

const canvasRef = ref(null);
const props = defineProps({
  active: Boolean
});

let interval = null;

const runMatrix = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Set canvas size
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    
    const fontSize = 16;
    const columns = canvas.width / fontSize;
    
    // Array of drops - one per column
    const drops = [];
    for(let x = 0; x < columns; x++)
        drops[x] = 1; 

    // Clear previous interval if exists
    if (interval) clearInterval(interval);

    // Clear canvas entirely to start fresh
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Tracker-style aesthetics
    const trackerChars = 'CDEFGAB#0123456789.-|';
    const matrixChars = trackerChars.split('');
    const trackerColor = 'rgba(33, 241, 235)'; // Cyan from TrackerOverlay

    const draw = () => {
        // Fade out previous frame to create trails
        // destination-out reduces alpha of existing pixels, fading them to transparent
        ctx.save();
        ctx.globalCompositeOperation = 'destination-out';
        ctx.fillStyle = 'rgba(0, 0, 0, 0.3)'; 
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.restore();
        
        ctx.textAlign = 'center';
        ctx.fillStyle = trackerColor; 
        ctx.font = 'bold ' + fontSize + 'px "Courier New", monospace';
        
        // Add Glow
        ctx.shadowBlur = 4;
        ctx.shadowColor = trackerColor;
        
        for(let i = 0; i < drops.length; i++) {
            // Random tracker character
            const text = matrixChars[Math.floor(Math.random() * matrixChars.length)];
            
            ctx.fillText(text, i * fontSize, drops[i] * fontSize);
                        
            // Increment Y
            drops[i]++;
        }
        
        // Reset Shadow for next frame
        ctx.shadowBlur = 0;
    };
    
    interval = setInterval(draw, 33);
};

// Monitor active state
watch(() => props.active, (newVal) => {
    if (newVal) {
        nextTick(() => {
            runMatrix();
        });
    } else {
        if (interval) clearInterval(interval);
    }
});

onMounted(() => {
    // If active on mount (unlikely but possible), run it
    if (props.active) {
        runMatrix();
    }
    
    window.addEventListener('resize', () => {
        if (canvasRef.value && props.active) {
            // Restart matrix on resize to handle columns
            runMatrix();
        }
    });
});

onUnmounted(() => {
    if (interval) clearInterval(interval);
});
</script>

<template>
  <div class="matrix-container" v-if="active">
    <canvas ref="canvasRef"></canvas>
  </div>
</template>

<style scoped>
.matrix-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: 9999; /* Top level */
    pointer-events: none; 
    opacity: 0.4; /* Adjust global opacity here */
    /* removed background: black; */
}
canvas {
    display: block;
}
</style>
