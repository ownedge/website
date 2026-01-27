<script setup>
</script>

<template>
  <div class="noise-overlay"></div>
</template>

<style scoped>
.noise-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 35; /* Above Bootloader (30), Below Scanlines (50) */
  opacity: 0.18;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='1.2' numOctaves='1' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='1'/%3E%3C/svg%3E");
  background-repeat: repeat;
  animation: noiseAnimation 0.4s infinite;
}

@keyframes noiseAnimation {
  0% { background-position: 0 0; }
  10% { background-position: -5px -10px; }
  20% { background-position: -15px 5px; }
  30% { background-position: 7px -25px; }
  40% { background-position: 20px 25px; }
  50% { background-position: -25px 10px; }
  60% { background-position: 15px 5px; }
  70% { background-position: 0px 15px; }
  80% { background-position: 25px 35px; }
  90% { background-position: -10px 10px; }
}
</style>
