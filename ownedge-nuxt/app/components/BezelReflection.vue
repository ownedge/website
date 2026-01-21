<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const canvasRef = ref(null);
let animationFrameId = null;

const draw = () => {
    const canvas = canvasRef.value;
    if (!canvas) {
        animationFrameId = requestAnimationFrame(draw);
        return;
    }
    
    // Auto-resize
    const targetW = window.innerWidth;
    const targetH = window.innerHeight;
    if (canvas.width !== targetW || canvas.height !== targetH) {
        canvas.width = targetW;
        canvas.height = targetH;
    }
    
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Only draw on desktop
    if (canvas.width > 900) {
        // Find scroll container directly to avoid reactivity lag
        const scrollContainer = document.querySelector('.scroll-content');
        const scrollTop = scrollContainer ? scrollContainer.scrollTop : 0;
        const windowH = window.innerHeight;
        
        // --- BEZEL GLOW LOGIC ---
        // Aligned with user-tuned values
        const heroHeight = windowH - 172; 
        
        // Detect Safari (rough check for "different opacity" requirement)
        const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
        const opacity = isSafari ? 0.025 : 0.15;
        
        // 1. Menu Bar Glow
        const menuY = heroHeight - scrollTop + 0; 
        
        if (menuY > -50 && menuY < windowH + 50) {
             ctx.filter = "blur(20px)"; // Menu Blur
             ctx.fillStyle = `rgba(255, 255, 255, ${opacity})`;
             ctx.fillRect(0, menuY, canvas.width, 44);
        }
        
        // 2. Footer Glow
        const footerY = heroHeight + windowH - 222 - scrollTop;
        
        if (footerY > -50 && footerY < windowH + 50) {
             ctx.filter = "blur(6px)"; // Footer Blur (Thinner line needs less blur or it vanishes)
             ctx.fillStyle = `rgba(255, 255, 255, ${opacity})`;
             ctx.fillRect(0, footerY, canvas.width, 3); 
        }
        
        // Reset filter so the bezel mask ITSELF is sharp
        ctx.filter = "none";
        
        // --- BEZEL MASK (Constrain to 11px Border) ---
        // Keeps pixels only where the plastic bezel would be.
        ctx.globalCompositeOperation = 'destination-in';
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 11;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        const padX = 40;
        const padTop = 40;
        const padBot = 80;
        const borderW = 11;
        // The outer CSS radius is 40px. 
        // We stroke strictly along the center (offset 5.5px).
        // Mathematically, radius should be 34.5px.
        // However, visually adjusting to 38px to match user expectation of "40px radius".
        const strokeRadius = 38; 
        
        const offset = borderW / 2;
        const mx = padX + offset;
        const my = padTop + offset;
        const mw = canvas.width - (padX * 2) - borderW;
        const mh = canvas.height - padTop - padBot - borderW;
        
        ctx.beginPath();
        if (ctx.roundRect) {
            ctx.roundRect(mx, my, mw, mh, strokeRadius);
        } else {
            // Fallback for older browsers
            ctx.moveTo(mx + strokeRadius, my);
            ctx.arcTo(mx + mw, my, mx + mw, my + mh, strokeRadius);
            ctx.arcTo(mx + mw, my + mh, mx, my + mh, strokeRadius);
            ctx.arcTo(mx, my + mh, mx, my, strokeRadius);
            ctx.arcTo(mx, my, mx + mw, my, strokeRadius);
        }
        ctx.closePath();
        ctx.stroke();
        
        // --- BOTTOM MASK (Hide Reflection on Bottom Border) ---
        // Requested: Cover the 11px high bottom border to HIDE reflection logic.
        ctx.globalCompositeOperation = 'destination-out';
        ctx.fillStyle = '#000'; // Color irrelevant
        
        // Bottom border Y position:
        // mh = height of the stroked rect (approx inner height)
        // my = top of stroked rect
        // The Border is stroke centered at my+mh.
        // It extends from my + mh - 5.5 to my + mh + 5.5.
        // Or simpler: The border is at `canvas.height - padBot - borderW` to `canvas.height - padBot`.
        // padBot = 80.
        // borderW = 11.
        // So bottom bezel is from Y = `canvas.height - 80 - 11` to `canvas.height - 80`.
        
        // We want to mask this exact strip.
        const maskY = canvas.height - padBot - borderW;
        const maskH = borderW + 2; // +2 just to be safe
        
        ctx.fillRect(0, maskY, canvas.width, maskH);
        
        
        // Reset composite for next frame clearing
        ctx.globalCompositeOperation = 'source-over';
    }
    
    animationFrameId = requestAnimationFrame(draw);
};

onMounted(() => {
    draw();
});

onUnmounted(() => {
    if (animationFrameId) cancelAnimationFrame(animationFrameId);
});
</script>

<template>
    <div class="bezel-reflection-layer">
        <canvas ref="canvasRef"></canvas>
    </div>
</template>

<style scoped>
.bezel-reflection-layer {
    position: absolute;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    pointer-events: none;
    z-index: 100; /* Same as TrackerOverlay used to be for reflection */
    mix-blend-mode: screen; /* Optional: Nice for light reflection */
}

canvas {
    display: block;
}
</style>
