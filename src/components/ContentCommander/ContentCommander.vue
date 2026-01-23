<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import SoundManager from '../../sfx/SoundManager';
import WhySection from './WhySection.vue';
import WhatSection from './WhatSection.vue';
import BlogSection from './BlogSection.vue';
import GuestbookSection from './GuestbookSection.vue';
import ChatSection from './ChatSection.vue';
import TabSwitcher from './TabSwitcher.vue';

const props = defineProps({
  tabs: { type: Array, required: true },
  activeIndex: { type: Number, default: 0 }
});

const emit = defineEmits(['update:activeIndex', 'reset-settings']);

const sections = {
  what: WhatSection,
  why: WhySection,
  guestbook: GuestbookSection,
  chat: ChatSection
};

const activeKey = ref(null);
const viewportContent = ref(null);

const activeTab = computed(() => {
    const tabData = props.tabs[props.activeIndex];
    return sections[tabData.id] || null;
});

const selectTab = (index) => {
  if (props.activeIndex !== index) {
      emit('update:activeIndex', index);
      SoundManager.playHoverSound();
      
      // If we are peeking, the parent (App.vue) will handle the scroll via activeIndex change
      if (viewportContent.value) viewportContent.value.scrollTop = 0;
  }
};

// Keyboard Navigation
const handleKeydown = (e) => {
    if (e.defaultPrevented) return;

    // F-keys visual feedback
    if (e.key.startsWith('F')) {
        e.preventDefault();
        if (activeKey.value !== e.key) SoundManager.playTypingSound();
        activeKey.value = e.key;
        return;
    }

    // Tab switching is handled globally by App.vue to ensure consistent behavior 
    // and support for the Easter Egg navigation lock.

    // Content Scroll
    if (e.key === 'ArrowDown') {
        if (viewportContent.value) viewportContent.value.scrollTop += 40;
    } else if (e.key === 'ArrowUp') {
        if (viewportContent.value) viewportContent.value.scrollTop -= 40;
    }
}

const handleKeyup = (e) => {
    if (e.key.startsWith('F')) {
        activeKey.value = null;
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('keyup', handleKeyup);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('keyup', handleKeyup);
});

</script>

<template>
  <div class="tui-container">
    <div class="tui-frame">
      <!-- Tab Bar (sit at absolute top) -->
      <TabSwitcher 
        :tabs="tabs" 
        :active-index="activeIndex" 
        @select="selectTab"
        @reset-settings="emit('reset-settings')"
      />

      <!-- Main Contents -->
      <div class="tui-viewport custom-scroll" :class="{ 'no-overflow': activeTab === ChatSection }" ref="viewportContent">
          <component :is="activeTab" v-if="activeTab" />
          <div v-else class="home-placeholder">
              <!-- No content for HOME tab inside Commander, as it maps back to Hero -->
          </div>
      </div>

      <!-- Bottom Function Keys -->
      <div class="tui-footer">
        <div class="f-key" :class="{ active: activeKey === 'F1' }"><span>F1</span> <span class="f-label">HELP</span></div>
        <div class="f-key" :class="{ active: activeKey === 'F2' }"><span>F2</span> <span class="f-label">LINK</span></div>
        <div class="f-key" :class="{ active: activeKey === 'F3' }"><span>F3</span> <span class="f-label">VIEW</span></div>
        <div class="f-key" :class="{ active: activeKey === 'F4' }"><span>F4</span> <span class="f-label">QUIT</span></div>
        <div class="f-key sys-status"><span>ONLINE</span></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.tui-container {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  padding: 20px 0 0 0;
  background-color: transparent;
  font-family: 'Microgramma', monospace; 
  color: #fff;
  box-sizing: border-box;
}

.tui-frame {
  width: 100%;
  height: 100%;
  background: transparent;
  display: flex;
  flex-direction: column;
}

/* Tab Bar Override (if needed) */

/* Tab Bar */
.tui-tab-bar {
    display: flex;
    padding: 0;
    gap: 0;
    align-items: stretch;
    height: 44px;
    margin: 15px 0; /* Margin for overlapping mask */
}

.tui-tab-bar::before {
    content: '';
    width: 30px; /* Left-side cap */
    background: #fff;
}

.tui-tab-bar::after {
    content: '';
    flex: 1; /* Pushes tabs to the left */
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
    transition: none;
    height: 100%;
}

.tui-tab:hover:not(.active) {
    background: #f0f0f0;
}

.tui-tab.active {
    background: transparent;
    color: #fff;
    height: 64px; /* Taller than the bar */
    align-self: center;
    position: relative;
    z-index: 5;
}

.tab-name {
    font-size: 0.85rem;
    font-weight: bold;
    letter-spacing: 1px;
}

/* Viewport Area */
.tui-viewport {
  flex: 1;
  padding: 30px 40px;
  overflow-y: auto;
  font-size: 1.2rem;
  line-height: 1.5;
  display: flex;
  flex-direction: column;
}

.tui-viewport.no-overflow {
    overflow: hidden;
    padding: 30px 40px 0; /* Align title vertical position with other pages */
}

/* Custom Scroll */
.custom-scroll::-webkit-scrollbar { width: 6px; }
.custom-scroll::-webkit-scrollbar-track { background: transparent; }
.custom-scroll::-webkit-scrollbar-thumb { background: #333; }

/* Footer */
.tui-footer {
    display: flex;
    padding: 15px 30px;
    gap: 20px;
    border-top: 2px solid #fff;
    background: rgba(255,255,255,0.02);
}

.f-key {
    font-size: 0.9rem;
    color: #666;
    display: flex;
    align-items: center;
}

.f-key span:first-child {
    background: #333;
    color: #ccc;
    padding: 2px 6px;
    margin-right: 6px;
    font-weight: bold;
}

.f-key.active span:first-child {
    background: var(--color-accent);
    color: #000;
}

.sys-status {
    margin-left: auto;
    color: var(--color-accent);
    font-weight: bold;
}

/* Mobile Responsive */
@media (max-width: 900px) {
    .tui-viewport {
        padding: 20px 15px;
        font-size: 1rem;
        scrollbar-width: none;
    }

    .tui-viewport::-webkit-scrollbar {
        display: none;
    }

    .tui-footer {
        display: none !important;
    }

    .tui-container {
        padding: 0;
    }
}
</style>
