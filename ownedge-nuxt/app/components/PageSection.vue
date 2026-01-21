<template>
  <section class="page-section">
      <slot />
  </section>
</template>

<style scoped>
.page-section {
  height: 100%; /* Fit the scroll-content container exactly */
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  scroll-snap-align: start;
  position: relative;
}
</style>
