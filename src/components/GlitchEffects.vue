<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  active: { type: Boolean, default: true }
});

// --- 1. Standard "Spherical Warp" Glitch ---
// This drives the #spherical-warp filter (currently a placeholder in App.vue, 
// but we will define the real one here if we have the math, or keep the placeholder logic driven by this).
// Note: In the original App.vue, 'turbulenceFreq' was defined but the SVG filter utilizing it 
// seemed to be missing or implicitly handled. We will define a basic turbulence filter for it here
// so the logic actually does something visible.
const turbulenceFreq = ref(0.0002);

const triggerGlitch = () => {
    if (!props.active) return;

    // Glitch sequence: Spike -> Recover -> Minor Spike -> Recover
    const spike = () => {
       turbulenceFreq.value = 0.0044 * Math.random();
       setTimeout(() => {
           turbulenceFreq.value = 0.0002; 
       }, 50 + Math.random() * 100);
    };

    spike();
    
    // Occasionally double glitch
    if (Math.random() > 0.5) {
        setTimeout(spike, 150);
    }
    
    // Schedule next glitch
    setTimeout(triggerGlitch, Math.random() * 8000 + 2000); 
};

// --- 2. Wave/Displacement Glitch ---
// This drives the #wave-glitch filter
const glitchWaveStrength = ref(0);

const triggerWaveGlitch = () => {
    if (!props.active) return;

    // Smooth ease-in-out wave distortion
    const intensity = Math.random() > 0.8 ? 50 : 20; 
    const duration = 300; // ms duration for the wave to pass
    const startTime = performance.now();

    const animate = (now) => {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Sinusoidal wave 0 -> 1 -> 0 for ease-in-out
        const ease = Math.sin(progress * Math.PI); 
        
        glitchWaveStrength.value = intensity * ease;

        if (progress < 1) {
            requestAnimationFrame(animate);
        } else {
            glitchWaveStrength.value = 0;
            // Schedule next glitch
            setTimeout(triggerWaveGlitch, Math.random() * 6000 + 6000); // Every 6-12s
        }
    };
    
    requestAnimationFrame(animate);
};

onMounted(() => {
    if (props.active) {
        triggerGlitch();
        // Delay initial wave glitch slightly
        setTimeout(() => triggerWaveGlitch(), 1500);
    }
});
</script>

<template>
    <!-- SVG Filters for CRT Effects -->
    <svg style="position: absolute; width: 0; height: 0; pointer-events: none;">
      <defs>
        <!-- Wave Glitch (Horizontal Distortion) -->
        <filter id="wave-glitch" x="-20%" y="-20%" width="140%" height="140%">
            <feTurbulence type="fractalNoise" baseFrequency="0.001 0.2" numOctaves="1" result="noise" />
            <feDisplacementMap in="SourceGraphic" in2="noise" :scale="glitchWaveStrength" xChannelSelector="R" yChannelSelector="R" />
        </filter>

        <!-- Spherical Warp / Turbulence Glitch -->
        <!-- We use the turbulenceFreq ref to drive the baseFrequency here -->
        <filter id="spherical-warp" x="-20%" y="-20%" width="140%" height="140%">
            <!-- Using the ref value for frequency y component to create vertical jitter/tearing -->
            <feTurbulence type="fractalNoise" :baseFrequency="`0.0002 ${turbulenceFreq}`" numOctaves="1" result="noise" />
            <!-- Very subtle displacement usually, spiking during glitch -->
            <!-- If turbulenceFreq is very low (0.0002), this effect is negligible. When it spikes (0.0044), it distorts. -->
            <!-- We need a displacement map to actually apply the noise -->
            <feDisplacementMap in="SourceGraphic" in2="noise" scale="10" xChannelSelector="R" yChannelSelector="G" />
        </filter>
      </defs>
    </svg>
</template>
