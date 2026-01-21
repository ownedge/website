<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import ScrollContainer from '~/components/ScrollContainer.vue'
import PageSection from '~/components/PageSection.vue'
import HeroDisplay from '~/components/HeroDisplay.vue'
import CommanderWrapper from '~/components/CommanderWrapper.vue'

// Parallax Logic
const mouseX = ref(0)
const mouseY = ref(0)
const windowWidth = ref(1024)
const windowHeight = ref(768)

const updateMouse = (e) => {
    mouseX.value = e.clientX
    mouseY.value = e.clientY
}
const handleResize = () => {
    if (import.meta.client) {
        windowWidth.value = window.innerWidth
        windowHeight.value = window.innerHeight
    }
}

onMounted(() => {
    window.addEventListener('mousemove', updateMouse)
    window.addEventListener('resize', handleResize)
    handleResize()
})
onUnmounted(() => {
    window.removeEventListener('mousemove', updateMouse)
    window.removeEventListener('resize', handleResize)
})

const heroStyle = computed(() => {
  const x = (mouseX.value - windowWidth.value / 2) * 0.005
  const y = (mouseY.value - windowHeight.value / 2) * 0.005
  return { transform: `translate(${-x}px, ${-y}px)` }
})

useHead({
  title: 'Ownedge | Independent by Design',
  meta: [
    { name: 'description', content: 'Defying the establishment. A digital window for independent creators and builders.' },
    { property: 'og:title', content: 'Ownedge | Independent by Design' },
    { property: 'og:description', content: 'Defying the establishment. A digital window for independent creators and builders.' },
    { property: 'og:image', content: 'https://ownedge.com/favicon-master.png' },
    { name: 'theme-color', content: '#0d0d0d' }
  ]
})
</script>

<template>
  <ScrollContainer initial-scroll="top">
      <PageSection class="hero-section" :style="heroStyle">
          <HeroDisplay />
      </PageSection>
      <PageSection>
           <CommanderWrapper>
               <!-- Empty Home Placeholder -->
           </CommanderWrapper>
      </PageSection>
  </ScrollContainer>
</template>

<style scoped>
.hero-section {
  height: calc(100% - 120px) !important; 
  transition: transform 0.1s ease-out;
}
@media (max-width: 900px) {
    .hero-section { height: 100% !important; }
}
</style>
