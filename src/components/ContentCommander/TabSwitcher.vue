<script setup>
import { ref, watch, onMounted, nextTick, onUnmounted } from 'vue';

const props = defineProps({
  tabs: { type: Array, required: true },
  activeIndex: { type: Number, default: 0 }
});

const emit = defineEmits(['select']);
const tabRefs = ref([]);
const indicatorStyle = ref({});
const tabBarRef = ref(null);
const showLeftArrow = ref(false);
const showRightArrow = ref(false);

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
        
        // Auto-scroll into view on mobile
        if (tabBarRef.value && window.innerWidth <= 900) {
             const container = tabBarRef.value;
             const tabLeft = activeTab.offsetLeft;
             const tabWidth = activeTab.offsetWidth;
             const containerWidth = container.clientWidth;
             const scrollLeft = container.scrollLeft;
             
             if (tabLeft < scrollLeft || (tabLeft + tabWidth) > (scrollLeft + containerWidth)) {
                 container.scrollTo({
                     left: tabLeft - (containerWidth / 2) + (tabWidth / 2),
                     behavior: 'smooth'
                 });
             }
        }
    }
};

const checkScroll = () => {
    if (!tabBarRef.value) return;
    const el = tabBarRef.value;
    // Check if scrollable
    if (el.scrollWidth > el.clientWidth) {
        showLeftArrow.value = el.scrollLeft > 5; // buffer
        showRightArrow.value = (el.scrollLeft + el.clientWidth) < (el.scrollWidth - 5);
    } else {
        showLeftArrow.value = false;
        showRightArrow.value = false;
    }
};

watch(() => props.activeIndex, () => {
    nextTick(() => {
        updateIndicator();
        /* small delay for scroll updates */
        setTimeout(checkScroll, 300); 
    });
});

onMounted(() => {
    // Wait for layout
    setTimeout(() => {
        updateIndicator();
        checkScroll();
    }, 100);
    window.addEventListener('resize', updateIndicator);
    window.addEventListener('resize', checkScroll);
    if (tabBarRef.value) {
        tabBarRef.value.addEventListener('scroll', checkScroll);
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', updateIndicator);
    window.removeEventListener('resize', checkScroll);
    if (tabBarRef.value) {
        tabBarRef.value.removeEventListener('scroll', checkScroll);
    }
});
</script>

<template>
  <div class="tab-container">
      <div class="scroll-arrow left" v-if="showLeftArrow">◄</div>
      <div ref="tabBarRef" class="tui-tab-bar">
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
      <div class="scroll-arrow right" v-if="showRightArrow">►</div>
  </div>
</template>

<style scoped>
.tab-container {
    width: 100%;
    position: relative;
    display: flex;
    align-items: center;
}

.tui-tab-bar {
    display: flex;
    padding: 0;
    gap: 0;
    align-items: flex-end; /* Ground the tabs so they expand upwards smoothly */
    height: 44px; /* Total height of largest tab */
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

.tui-tab:focus {
    outline: none; /* Remove default browser blue outline */
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

.scroll-arrow {
    display: none;
}

@media (max-width: 900px) {
    .tui-tab-bar {
        overflow-x: auto;
        padding-bottom: 5px; /* Space for scrollbar if any */
        height: 50px;
        scrollbar-width: none;
        scroll-behavior: smooth; /* Enable smooth scrolling */
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
    
    /* Extend Menu Bar to Right Edge on Mobile */
    .tui-tab-bar::after {
        content: '';
        display: block;
        flex: 1;
        background: #fff;
        height: 40px; /* Match mobile tab height */
        min-width: 20px; /* Ensure some extension even if cramped */
    }

    /* Scroll Indicators */
    @keyframes pulse-opacity {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.3; }
    }

    .scroll-arrow {
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 1.9rem;
        width: 20px;
        height: 40px;
        position: absolute;
        top: 5px; /* Adjust to vertical center of tabs */
        z-index: 20;
        pointer-events: none; /* Let touches pass through */
        animation: pulse-opacity 2s infinite ease-in-out;
        mix-blend-mode: normal;
        color: #000000; /* Difference with white makes it inverse of bg */
        padding-bottom: 4px; /* visual adjust: lift text up */
    }

    .scroll-arrow.left {
         left: 0;
    }

    .scroll-arrow.right {
         right: 0;
    }
}
</style>
