<script setup>
import WhatSection from '~/components/ContentCommander/WhatSection.vue'

useHead({
  title: 'Ownedge | What We Do',
  meta: [
    { name: 'description', content: 'Exploring the boundaries of digital products, strategy, and engineering.' },
    { property: 'og:title', content: 'Ownedge | What We Do' },
    { property: 'og:description', content: 'Exploring the boundaries of digital products, strategy, and engineering.' }
  ]
})
</script>

<template>
  <ScrollContainer initial-scroll="bottom">
      <PageSection class="hero-section">
          <HeroDisplay />
      </PageSection>
      <PageSection>
           <CommanderWrapper>
               <WhatSection />
           </CommanderWrapper>
      </PageSection>
  </ScrollContainer>
</template>

<style scoped>
.hero-section {
  height: calc(100% - 120px) !important; 
}
@media (max-width: 900px) {
    .hero-section { height: 100% !important; }
}
</style>
