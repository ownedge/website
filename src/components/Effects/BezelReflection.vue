<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const canvasRef = ref(null);

const draw = () => {
    const canvas = canvasRef.value;
    if (!canvas) return;
    
    const targetW = window.innerWidth;
    const targetH = window.innerHeight;
    
    // Resize if needed
    if (canvas.width !== targetW || canvas.height !== targetH) {
        canvas.width = targetW;
        canvas.height = targetH;
    }
    
    const scrollContainer = document.querySelector('.scroll-content');
    const scrollTop = scrollContainer ? scrollContainer.scrollTop : 0;
    
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Only draw on desktop
    if (canvas.width > 900) {
        const windowH = window.innerHeight;
        // Aligned with user-tuned values (windowH - 172)
        const heroHeight = windowH - 172; 
        
        const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
        const menuOpacity = isSafari ? 0.055 : 0.07;
        const footerOpacity = isSafari ? 0.025 : 0.025;
        
        // 1. Menu Bar Glow
        const menuY = heroHeight - scrollTop; 
        
        if (menuY > -100 && menuY < windowH + 100) {
             const menuH = 44;
             const blur = 18;
             const g = ctx.createLinearGradient(0, menuY - blur, 0, menuY + menuH + blur);
             g.addColorStop(0, `rgba(255, 255, 255, 0)`);
             g.addColorStop(0.3, `rgba(255, 255, 255, ${menuOpacity})`);
             g.addColorStop(0.7, `rgba(255, 255, 255, ${menuOpacity})`);
             g.addColorStop(1, `rgba(255, 255, 255, 0)`);
             
             ctx.fillStyle = g;
             ctx.fillRect(0, menuY - blur, canvas.width, menuH + blur * 2);
        }
        
        // 2. Footer Glow
        const footerY = heroHeight + windowH - 232 - scrollTop;
        
        if (footerY > -50 && footerY < windowH + 50) {
             const footerH = 3;
             const blur = 10;
             const g = ctx.createLinearGradient(0, footerY - blur, 0, footerY + footerH + blur);
             g.addColorStop(0, `rgba(255, 255, 255, 0)`);
             g.addColorStop(0.4, `rgba(255, 255, 255, ${footerOpacity})`);
             g.addColorStop(0.6, `rgba(255, 255, 255, ${footerOpacity})`);
             g.addColorStop(1, `rgba(255, 255, 255, 0)`);

             ctx.fillStyle = g;
             ctx.fillRect(0, footerY - blur, canvas.width, footerH + blur * 2); 
        }
        
        ctx.filter = "none";
        
        // --- BEZEL MASK ---
        ctx.globalCompositeOperation = 'destination-in';
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 11;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        const padX = 40;
        const padTop = 43;
        const padBot = 83;
        const borderW = 11;
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
            ctx.moveTo(mx + strokeRadius, my);
            ctx.arcTo(mx + mw, my, mx + mw, my + mh, strokeRadius);
            ctx.arcTo(mx + mw, my + mh, mx, my + mh, strokeRadius);
            ctx.arcTo(mx, my + mh, mx, my, strokeRadius);
            ctx.arcTo(mx, my, mx + mw, my, strokeRadius);
        }
        ctx.closePath();
        ctx.stroke();
        
        // --- BOTTOM MASK ---
        ctx.globalCompositeOperation = 'destination-out';
        ctx.fillStyle = '#000'; 
        
        const maskY = canvas.height - padBot - borderW;
        const maskH = borderW + 2; 
        ctx.fillRect(0, maskY, canvas.width, maskH);
        
        ctx.globalCompositeOperation = 'source-over';
    }
};

let scrollEl = null;

const setupListeners = () => {
    // Wait for DOM
    draw();
    window.addEventListener('resize', draw);
    
    // Find scroll element
    scrollEl = document.querySelector('.scroll-content');
    if (scrollEl) {
        scrollEl.addEventListener('scroll', draw, { passive: true });
    } else {
        // Retry if not mounted yet (though onMounted should catch it)
        setTimeout(setupListeners, 100);
    }
};

onMounted(() => {
    // Initial delay to ensure parent DOM exists
    setTimeout(setupListeners, 50);
});

onUnmounted(() => {
    window.removeEventListener('resize', draw);
    if (scrollEl) scrollEl.removeEventListener('scroll', draw);
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
