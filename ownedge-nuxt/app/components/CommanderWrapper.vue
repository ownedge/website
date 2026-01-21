<script setup>
import TabSwitcher from '~/components/ContentCommander/TabSwitcher.vue'
import SoundManager from '~/utils/sfx/SoundManager'
import { ref, onMounted, onUnmounted } from 'vue'

// F-keys visual state
const activeKey = ref(null)

const handleKeydown = (e) => {
    if (e.defaultPrevented) return;
    if (e.key.startsWith('F')) {
        e.preventDefault();
        if (activeKey.value !== e.key && SoundManager && SoundManager.playTypingSound) {
             SoundManager.playTypingSound();
        }
        activeKey.value = e.key;
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
      <!-- Tab Bar -->
      <TabSwitcher />

      <!-- Main Contents -->
      <div class="tui-viewport custom-scroll">
          <slot />
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
    background: #40e0d0; /* Accent Color */
    color: #000;
}

.sys-status {
    margin-left: auto;
    color: #40e0d0;
    font-weight: bold;
}

/* Mobile Responsive */
@media (max-width: 900px) {
    .tui-viewport {
        padding: 20px 15px;
        font-size: 1rem;
        scrollbar-width: none;
    }
    .tui-viewport::-webkit-scrollbar { display: none; }
    .tui-footer { display: none !important; }
    .tui-container { padding: 0; }
}
</style>
