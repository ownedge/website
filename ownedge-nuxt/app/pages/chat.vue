<script setup>
import ChatSection from '~/components/ContentCommander/ChatSection.vue'

useHead({
  title: 'Ownedge | Terminal Cluster',
  meta: [
    { name: 'description', content: 'Communicate in real-time with other nodes connected to the Ownedge cluster.' },
    { property: 'og:title', content: 'Ownedge | Terminal Cluster' },
    { property: 'og:description', content: 'Communicate in real-time with other nodes connected to the Ownedge cluster.' }
  ]
})
</script>

<template>
  <ScrollContainer initial-scroll="bottom">
      <PageSection class="hero-section">
          <HeroDisplay />
      </PageSection>
      <PageSection style="height: auto; min-height: 100%;">
           <!-- Chat wrapper needs to handle overflow specifically? -->
           <!-- CommanderWrapper handles scrolling via tui-viewport -->
           <CommanderWrapper>
               <ChatSection />
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
