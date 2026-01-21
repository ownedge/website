<script setup>
import GuestbookSection from '~/components/ContentCommander/GuestbookSection.vue'

useHead({
  title: 'Ownedge | Leave Your Mark',
  meta: [
    { name: 'description', content: 'Sign the guestbook and join the lineage of terminal users.' },
    { property: 'og:title', content: 'Ownedge | Leave Your Mark' },
    { property: 'og:description', content: 'Sign the guestbook and join the lineage of terminal users.' }
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
               <GuestbookSection />
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
