<script setup>
import { onMounted } from 'vue';

const props = defineProps({
    initialScroll: { type: String, default: 'top' } // 'top' or 'bottom'
});

onMounted(() => {
    // If not Home (and initialScroll is bottom), scroll down
    if (props.initialScroll === 'bottom') {
        const el = document.querySelector('.scroll-content');
        if (el) {
             // We need to scroll to the second section
             // Since sections are 100% height
             el.scrollTop = window.innerHeight;
        }
    }
});
</script>

<template>
  <div class="scroll-content">
      <slot />
  </div>
</template>

<style scoped>
.scroll-content {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  overflow-y: auto; /* Enable scrolling internal to this div */
  scroll-snap-type: y mandatory;
  scroll-behavior: smooth;
  z-index: 20; /* Above background but below overlays */
  
  /* Hide scrollbar */
  scrollbar-width: none;
}

.scroll-content::-webkit-scrollbar {
  display: none;
}
</style>
