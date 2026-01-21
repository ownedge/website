<script setup>
import WhySection from '~/components/ContentCommander/WhySection.vue'

useHead({
  title: 'Ownedge | Why We Exist',
  meta: [
    { name: 'description', content: 'The Ownedge manifesto: our vision for a more intentional, independent digital future.' },
    { property: 'og:title', content: 'Ownedge | Why We Exist' },
    { property: 'og:description', content: 'The Ownedge manifesto: our vision for a more intentional, independent digital future.' }
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
               <WhySection />
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
