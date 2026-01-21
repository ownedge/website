<script setup>
// Static tabs configuration
const tabs = [
  { name: 'HOME', path: '/' },
  { name: 'WHAT', path: '/what' },
  { name: 'WHY', path: '/why' },
  { name: 'GUESTBOOK', path: '/guestbook' },
  { name: 'CHAT', path: '/chat' }
];
</script>

<template>
  <div class="tui-tab-bar">
      <NuxtLink 
        v-for="tab in tabs" 
        :key="tab.path"
        :to="tab.path"
        class="tui-tab"
        active-class="active"
      >
        <span class="tab-name">{{ tab.name }}</span>
      </NuxtLink>
  </div>
</template>

<style scoped>
.tui-tab-bar {
    display: flex;
    padding: 0;
    gap: 0;
    align-items: flex-end; 
    height: 64px; 
    margin: 0;
    width: 100%;
}

.tui-tab-bar::before {
    content: '';
    width: 30px;
    background: #fff;
}

.tui-tab-bar::after {
    content: '';
    flex: 1;
    background: #fff;
    min-width: 15px;
}

.tui-tab {
    padding: 0 30px;
    cursor: pointer;
    color: #000;
    background: #fff;
    display: flex;
    align-items: center;
    height: 44px;
    transition: all 0.2s cubic-bezier(0.19, 1, 0.22, 1);
    box-sizing: border-box;
    text-decoration: none; /* Remove link underline */
}

.tui-tab:hover:not(.active) {
    background: #f0f0f0;
}

/* NuxtLink active class */
.tui-tab.active {
    background: transparent;
    color: #fff;
    height: 44px; 
    position: relative;
    z-index: 5;
    border-left: 1px solid #fff;
    border-right: 1px solid #fff;
    cursor: default;
}

.tab-name {
    font-size: 0.85rem;
    font-weight: bold;
    letter-spacing: 1px;
}

@media (max-width: 900px) {
    .tui-tab-bar {
        overflow-x: auto;
        padding-bottom: 5px;
        height: 50px;
        scrollbar-width: none;
    }
    .tui-tab-bar::-webkit-scrollbar { display: none; }
    .tui-tab-bar::before, .tui-tab-bar::after { display: none; }
    .tui-tab, .tui-tab.active { padding: 0 15px; flex-shrink: 0; height: 40px; }
    .tab-name { font-size: 0.75rem; }
}
</style>
