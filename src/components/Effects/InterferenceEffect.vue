<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';

const canvasRef = ref(null);
const isActive = ref(false);
let animationFrameId = null;

const slices = [];
let sourceImage = null;

const startGlitchEffect = (rect, text, style) => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    
    // Resize to window
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;

    // 1. Create offline canvas to rasterize text
    const offCanvas = document.createElement('canvas');
    offCanvas.width = rect.width;
    offCanvas.height = rect.height;
    const offCtx = offCanvas.getContext('2d');
    
    // 2. Draw Text (White)
    offCtx.fillStyle = '#FFFFFF';
    offCtx.font = style.font;
    offCtx.textAlign = 'center';
    offCtx.textBaseline = 'middle';
    offCtx.fillText(text, rect.width / 2, rect.height / 2);
    
    sourceImage = offCanvas; // Cache the source bitmap
    
    // 3. Create initial slices
    slices.length = 0;
    const sliceCount = 30; // Number of horizontal strips
    const sliceHeight = rect.height / sliceCount;
    
    for (let i = 0; i < sliceCount; i++) {
        slices.push({
            y: rect.top + (i * sliceHeight) - 45,
            h: sliceHeight,
            localY: i * sliceHeight, // Y inside the source image
            
            // Animation Props
            offsetX: 0,
            scaleX: 1,
            
            // Each slice has its own "frequency"
            freq: Math.random() * 0.5 + 0.1, 
            phase: Math.random() * Math.PI * 2,
            
            isStretched: Math.random() > 0.7 // Only some lines stretch wildly
        });
    }
    
    isActive.value = true;
    animateGlitch(rect);
};

const animateGlitch = (rect) => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    // Clear with slight trail for motion blur feel
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    if (!isActive.value) return;

    // Render Slices
    slices.forEach((s, i) => {
        // Update physics
        s.phase += s.freq;
        
        // Jitter
        s.offsetX = (Math.sin(s.phase) * Math.sin(s.phase * 3.7)) * 20; 
        
        // Horizontal Stretch
        if (s.isStretched) {
             // Oscillating stretch
             s.scaleX = 1 + Math.abs(Math.sin(s.phase * 5)) * 15; // Stretch up to 15x
             s.offsetX += (Math.random() - 0.5) * 100; // Wild jitter
        } else {
             s.scaleX = 1 + Math.random() * 0.2; // Subtle breathing
        }
        
        // Draw Slice
        const destW = rect.width * s.scaleX;
        const destH = s.h;
        const destX = rect.left + s.offsetX - (destW - rect.width) / 2; // Center the stretch
        
        ctx.save();
        
        // Color Split (Chromatic Aberration)
        // Red Channel (Offset Left)
        ctx.globalCompositeOperation = 'screen';
        ctx.globalAlpha = 0.8;
        ctx.fillStyle = '#FF0000';
         // Need to use drawImage but colorize... standard Canvas `drawImage` doesn't colorize easily without composite hacks.
         // Simpler approach for pure white text: Global Composite + Filters?
         // Optimized approach: Just draw the image slice multiple times with slight offsets for RGB feel
        
        // Main Pass (Cyan/White Tint)
        ctx.globalAlpha = 1;
        ctx.filter = `brightness(1.5) drop-shadow(0 0 5px #21f1eb)`; // Neon Glow
        
        if (sourceImage) {
            ctx.drawImage(
                sourceImage, 
                0, s.localY, rect.width, s.h, // Source
                destX, s.y, destW, destH      // Dest
            );
        }
        
        // Occasional RGB Split Artifact (Red Ghost)
        if (Math.random() > 0.8) {
             ctx.globalCompositeOperation = 'lighten';
             ctx.globalAlpha = 0.6;
             ctx.filter = 'hue-rotate(90deg)'; // Shift color
             ctx.drawImage(
                sourceImage, 
                0, s.localY, rect.width, s.h, 
                destX + (Math.random() * 50 - 25), s.y, destW * 1.1, destH
            );
        }
        
        ctx.restore();
    });
    
    // Scanline / Interference Line
    const yScan = Math.random() * canvas.height;
    ctx.fillStyle = 'rgba(255, 255, 255, 0.1)';
    ctx.fillRect(0, yScan, canvas.width, 2);

    animationFrameId = requestAnimationFrame(() => animateGlitch(rect));
};

// Auto-stop is handled by the parent transition logic mostly, 
// but we can ensure cleanup
const stop = () => {
    isActive.value = false;
    if (animationFrameId) cancelAnimationFrame(animationFrameId);
    const canvas = canvasRef.value;
    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }
};

defineExpose({
    explodeText: (rect, text, style) => {
        // Reset previous
        if (animationFrameId) cancelAnimationFrame(animationFrameId);
        
        // Run glitch for fixed time then stop (parent controls visibility of real text)
        startGlitchEffect(rect, text, style);
        
        // Cleanup after 1.5s (match HeroDisplay timeout)
        setTimeout(stop, 1500);
    }
});

onUnmounted(() => {
    if (animationFrameId) cancelAnimationFrame(animationFrameId);
});
</script>

<template>
    <div class="interference-container" :class="{ active: isActive }">
        <canvas ref="canvasRef"></canvas>
    </div>
</template>

<style scoped>
.interference-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    pointer-events: none;
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.1s;
}

.interference-container.active {
    opacity: 1;
}

canvas {
    display: block;
}
</style>
