<script setup>
</script>

<template>
  <div class="grid-overlay">
    <div class="grid-lines horizontal" v-for="i in 10" :key="'h-'+i" :style="{ top: (i * 10) + '%' }"></div>
    <div class="grid-lines vertical" v-for="i in 10" :key="'v-'+i" :style="{ left: (i * 10) + '%' }"></div>
    <div class="crosshair center"></div>
  </div>
</template>

<style scoped>
.grid-overlay {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 10;
  opacity: 0.25;
}

.grid-lines {
  position: absolute;
  background-color: var(--grid-line);
}

.horizontal {
  width: 100%;
  height: 1px;
  transform-origin: center;
  animation: drawLine 1.5s cubic-bezier(0.19, 1, 0.22, 1) forwards;
}

.vertical {
  height: 100%;
  width: 1px;
  transform-origin: top;
  animation: drawLineV 1.5s cubic-bezier(0.19, 1, 0.22, 1) forwards;
}

.crosshair.center {
  position: absolute;
  top: 50%;
  left: 50%;
  width: 40px;
  height: 40px;
  /* border-left: 1px solid rgba(255, 255, 255, 0.3); */
  /* border-top: 1px solid rgba(255, 255, 255, 0.3); */
  transform: translate(-50%, -50%);
}
.crosshair.center::before, .crosshair.center::after {
    content: '';
    position: absolute;
    background-color: rgba(255,255,255,0.3);
}
/* Cross shape */
.crosshair.center::before {
    top: 50%; left: 0; width: 100%; height: 1px; transform: translateY(-50%);
}
.crosshair.center::after {
    left: 50%; top: 0; height: 100%; width: 1px; transform: translateX(-50%);
}
</style>
