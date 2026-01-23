<script setup>
import { ref, watch, onMounted, nextTick } from 'vue';

const props = defineProps({
  tabs: { type: Array, required: true },
  activeIndex: { type: Number, default: 0 }
});

const emit = defineEmits(['select']);
const tabRefs = ref([]);
const indicatorStyle = ref({});

const selectTab = (index) => {
  emit('select', index);
};

const handleTabKeydown = (e, index) => {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        selectTab(index);
    }
};

const updateIndicator = () => {
    const activeTab = tabRefs.value[props.activeIndex];
    if (activeTab) {
        indicatorStyle.value = {
            left: `${activeTab.offsetLeft}px`,
            width: `${activeTab.offsetWidth}px`,
            opacity: 1
        };
    }
};

watch(() => props.activeIndex, () => {
    nextTick(updateIndicator);
});

onMounted(() => {
    // Wait for layout
    setTimeout(updateIndicator, 100);
    window.addEventListener('resize', updateIndicator);
});
</script>

<template>
  <div class="tui-tab-bar">
      <div class="active-indicator" :style="indicatorStyle"></div>
      <div 
        v-for="(tab, index) in tabs" 
        :key="tab.id"
        :ref="el => tabRefs[index] = el"
        class="tui-tab"
        :class="{ active: activeIndex === index }"
        @click="selectTab(index)"
        @keydown="(e) => handleTabKeydown(e, index)"
        tabindex="0"
        role="tab"
        :aria-selected="activeIndex === index"
      >
        <span class="tab-name">{{ tab.name }}</span>
      </div>
  </div>
</template>

<style scoped>
.tui-tab-bar {
    display: flex;
    padding: 0;
    gap: 0;
    align-items: flex-end; /* Ground the tabs so they expand upwards smoothly */
    height: 64px; /* Total height of largest tab */
    margin: 0;
    width: 100%;
    position: relative; /* Context for absolute indicator */
    overflow: hidden; /* To contain the massive shadow */
}

/* Shadow trick replaces pseudo elements for desktop background */
.tui-tab-bar::before,
.tui-tab-bar::after {
    display: none;
}

.tui-tab {
    padding: 0 30px;
    cursor: pointer;
    color: #000;
    background: transparent; /* Transparent to show 'hole' or shadow */
    display: flex;
    align-items: center;
    height: 44px; /* Base height */
    transition: color 0.3s linear;
    box-sizing: border-box; /* Crucial for border-based layout stability */
    position: relative;
    z-index: 2;
}

.tui-tab:hover:not(.active) {
    background: rgba(255, 255, 255, 0.1);
}

.tui-tab.active {
    background: transparent;
    color: #fff;
    height: 44px; /* Maintain consistent height */
    z-index: 5;
}

.active-indicator {
    position: absolute;
    bottom: 0;
    height: 44px;
    background: transparent;
    /* The Magic: Massive white shadow defines the bar, leaving this box as a transparent hole */
    box-shadow: 0 0 0 9999px #fff; 
    z-index: 1; /* Below text */
    pointer-events: none;
    transition: left 0.5s cubic-bezier(0.5, -0.1, 0.2, 1.4), width 0.4s ease;
    box-sizing: border-box;
    opacity: 1; 
}

.tab-name {
    font-size: 0.85rem;
    font-weight: bold;
    letter-spacing: 1px;
}
@media (max-width: 900px) {
    .tui-tab-bar {
        overflow-x: auto;
        padding-bottom: 5px; /* Space for scrollbar if any */
        height: 50px;
        scrollbar-width: none;
    }

    .tui-tab-bar::-webkit-scrollbar {
        display: none;
    }

    .tui-tab {
        background: #fff; /* Restore white cards for mobile */
        padding: 0 15px;
        flex-shrink: 0;
        height: 40px;
    }
    
    .tui-tab.active {
        background: transparent;
        padding: 0 15px;
        flex-shrink: 0;
        height: 40px;
    }
    
    .active-indicator {
        display: none; /* Hide sliding effect on mobile for simplicity, or adjust logic */
    }

    .tab-name {
        font-size: 0.75rem;
    }
}
</style>
