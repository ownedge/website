<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const canvasRef = ref(null);
let animationFrameId = null;

  const resize = () => {
    if (!canvasRef.value) return;
    const canvas = canvasRef.value;
    const ctx = canvas.getContext('2d', { alpha: false });
    // Use client dimensions for 1:1 pixel mapping, avoiding scaling issues
    // But be careful of high-DPI displays (Retina). 
    // For faithful retro look, 1:1 CSS pixels is fine, or we can use devicePixelRatio.
    // Let's stick to 1:1 CSS pixels for performance and exact alignment with getBoundingClientRect.
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width;
    canvas.height = rect.height;
    ctx.fillStyle = '#000';
    ctx.fillRect(0, 0, rect.width, rect.height);
  };
  
  const mouseX = ref(-100);
  const mouseY = ref(-100);

  const updateMouse = (e) => {
    mouseX.value = e.clientX;
    mouseY.value = e.clientY;
  };

  let frameCount = 0;
  let cachedTargets = [];
  const RE_SCAN_INTERVAL = 15; // Only re-query DOM every 15 frames

  const getCachedTargets = () => {
    // Re-query only occasionally and when visible
    if (frameCount % RE_SCAN_INTERVAL === 0) {
      cachedTargets = Array.from(document.querySelectorAll('.boot-video, .logo-img, .typing-wrapper, .status, .id, .percentage, .log-text, .btn-go, .hint-text, .tui-header .title, .tui-header .clock, .pane-title, .col-name, .col-type, .col-date, .pane-footer, .f-key span, .f-label, .view-content pre, .cursor, .tracker-overlay canvas'))
        .map(el => {
          const style = window.getComputedStyle(el);
          return {
            el,
            isImg: el.tagName === 'IMG' || el.tagName === 'VIDEO' || el.tagName === 'CANVAS',
            isPre: el.tagName === 'PRE',
            isTyping: el.classList.contains('typing-wrapper') || el.classList.contains('log-text'),
            color: style.color,
            font: `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`,
            letterSpacing: style.letterSpacing,
            textAlign: style.textAlign,
            paddingTop: parseFloat(style.paddingTop),
            paddingLeft: parseFloat(style.paddingLeft),
            paddingRight: parseFloat(style.paddingRight),
            borderLeftWidth: parseFloat(style.borderLeftWidth),
            lineHeight: style.lineHeight === 'normal' ? parseFloat(style.fontSize) * 1.2 : parseFloat(style.lineHeight),
            opacity: parseFloat(style.opacity)
          };
        });
    }
  };

  onMounted(() => {
    window.addEventListener('resize', resize);
    window.addEventListener('mousemove', updateMouse);
    
    setTimeout(resize, 0);

    const render = () => {
      if (!canvasRef.value) return;
      frameCount++;

      const canvas = canvasRef.value; 
      const ctx = canvas.getContext('2d', { alpha: false });
      const width = canvas.width;
      const height = canvas.height;

      // 1. Decay 
      ctx.globalCompositeOperation = 'source-over';
      ctx.fillStyle = 'rgba(0, 0, 0, 0.08)'; // Slightly faster decay for better perf
      ctx.fillRect(0, 0, width, height);

      // 2. Scan DOM (Throttled)
      getCachedTargets();

      // 3. Draw cursor trail
      if (mouseX.value > 0 && mouseY.value > 0) {
        const rect = canvas.getBoundingClientRect();
        const x = mouseX.value - rect.left;
        const y = mouseY.value - rect.top;
        ctx.globalCompositeOperation = 'lighter';
        ctx.fillStyle = '#FF0000';
        ctx.save();
        ctx.translate(x, y);
        ctx.beginPath();
        ctx.moveTo(0.6, 1.43);
        ctx.lineTo(14.14, 14.84);
        ctx.lineTo(6.78, 14.84);
        ctx.lineTo(6.38, 15.00);
        ctx.lineTo(0.6, 20.26);
        ctx.closePath();
        ctx.fill();
        ctx.restore();
      }

      // 4. Draw cached targets
      ctx.globalCompositeOperation = 'lighter';
      const canvasRect = canvas.getBoundingClientRect();

      cachedTargets.forEach(target => {
        const el = target.el;
        const rect = el.getBoundingClientRect();
        
        if (rect.bottom < 0 || rect.top > height || rect.right < 0 || rect.left > width) return;
        
        // Quick visibility check
        if (el.offsetWidth === 0 || el.offsetHeight === 0 || target.opacity === 0) return;

        const baseAlpha = target.isImg ? 0.05 : 0.4;
        ctx.globalAlpha = baseAlpha;
        
        const x = rect.left - canvasRect.left;
        const y = rect.top - canvasRect.top;
        const w = rect.width;
        const h = rect.height;
        
        ctx.save();

        if (el.matches('.view-content pre, .col-name, .col-type, .col-date')) {
            const scrollParent = el.closest('.pane-content');
            if (scrollParent) {
                const pRect = scrollParent.getBoundingClientRect();
                ctx.beginPath();
                ctx.rect(pRect.left - canvasRect.left, pRect.top - canvasRect.top, pRect.width, pRect.height);
                ctx.clip();
            }
        }
        
        if (target.isImg) {
           try {
               ctx.drawImage(el, x, y, w, h);
           } catch(e) {}
        } else {
           ctx.fillStyle = target.color;
           ctx.font = target.font;
           if (target.letterSpacing !== 'normal') ctx.letterSpacing = target.letterSpacing;

           const contentX = x + target.borderLeftWidth + target.paddingLeft;
           const contentW = w - (target.borderLeftWidth + target.paddingLeft + target.paddingRight);

           ctx.textAlign = (target.textAlign === 'center' || target.textAlign === 'right') ? target.textAlign : 'left';
           
           let drawX = contentX;
           if (target.textAlign === 'center') drawX = contentX + contentW / 2;
           if (target.textAlign === 'right') drawX = contentX + contentW;

           let textToDraw = el.innerText;
           if (target.isTyping) {
               textToDraw = Array.from(el.childNodes)
                 .filter(n => n.nodeType === Node.TEXT_NODE)
                 .map(n => n.textContent)
                 .join('');
           }

           if (target.isPre) {
                const lines = textToDraw.split('\n');
                ctx.textBaseline = 'top';
                lines.forEach((line, i) => {
                    ctx.fillText(line, drawX, y + target.paddingTop + (i * target.lineHeight) + 4); 
                });
           } else if (el.classList.contains('typing-wrapper')) {
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                ctx.fillText(textToDraw, contentX, y + h / 2);
           } else {
                ctx.textBaseline = 'middle';
                ctx.fillText(textToDraw, drawX, y + h / 2);
           }
        }
        ctx.letterSpacing = '0px';
        ctx.restore();
      });

      animationFrameId = requestAnimationFrame(render);
    };

    render();
  });

  onUnmounted(() => {
    window.removeEventListener('resize', resize);
    window.removeEventListener('mousemove', updateMouse);
    cancelAnimationFrame(animationFrameId);
  });
</script>

<template>
  <canvas ref="canvasRef" class="phosphor-canvas"></canvas>
</template>

<style scoped>
.phosphor-canvas {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 40; /* Above content (20), below Grid/Scanlines (50) */
  mix-blend-mode: screen;
  opacity: 0.13;
}
</style>
