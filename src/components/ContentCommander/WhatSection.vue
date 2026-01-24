<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import SoundManager from '../../sfx/SoundManager';

// Import logos
import barclaysLogo from '../../assets/collabs/barclays.jpg';
import controlinvestLogo from '../../assets/collabs/controlinvest.jpg';
import copidataLogo from '../../assets/collabs/copidata.jpeg';
import fluxygenLogo from '../../assets/collabs/fluxygen.png';
import kabisaLogo from '../../assets/collabs/kabisa.jpeg';
import lithoformasLogo from '../../assets/collabs/lithoformas.png';
import philipsLogo from '../../assets/collabs/philips.png';
import thalesLogo from '../../assets/collabs/thales.jpg';

const collabs = [
  { name: 'BARCLAYS', logo: barclaysLogo },
  { name: 'CONTROLINVEST', logo: controlinvestLogo },
  { name: 'COPIDATA', logo: copidataLogo },
  { name: 'FLUXYGEN', logo: fluxygenLogo },
  { name: 'KABISA', logo: kabisaLogo },
  { name: 'LITHOFORMAS', logo: lithoformasLogo },
  { name: 'PHILIPS', logo: philipsLogo },
  { name: 'THALES', logo: thalesLogo }
];

const businessTabs = [
  { id: 'services', name: 'SERVICES' },
  { id: 'collabs', name: 'COLLABS' }
];

const activeTabId = ref('services');

const selectTab = (id) => {
  if (activeTabId.value !== id) {
    activeTabId.value = id;
    SoundManager.playTypingSound();
  }
};

const handleKeydown = (e) => {
  const currentIndex = businessTabs.findIndex(t => t.id === activeTabId.value);
  
  if (e.key === 'ArrowDown') {
      e.preventDefault();
      e.stopImmediatePropagation();
      const nextIndex = (currentIndex + 1) % businessTabs.length;
      selectTab(businessTabs[nextIndex].id);
  } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      e.stopImmediatePropagation();
      const prevIndex = (currentIndex - 1 + businessTabs.length) % businessTabs.length;
      selectTab(prevIndex >= 0 ? businessTabs[prevIndex].id : businessTabs[businessTabs.length - 1].id);
  }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown, { capture: true });
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown, { capture: true });
});
</script>

<template>
  <div class="section-content animate-in">
    <h3>> WHAT</h3>
    
    <div class="business-layout">
      <!-- Left Menu -->
      <div class="business-menu">
        <div 
          v-for="tab in businessTabs" 
          :key="tab.id"
          class="menu-item"
          :class="{ active: activeTabId === tab.id }"
          @click="selectTab(tab.id)"
        >
          <span class="indicator">></span>
          <span class="label">{{ tab.name }}</span>
        </div>
      </div>

      <!-- Right Content -->
      <div class="business-viewport">

        <Transition name="fade">
          <div v-if="activeTabId === 'services'" key="services" class="tab-content">
            <h4>SERVICES</h4>
            <div class="service-grid">
              <div class="service-block">
                <h5>INTERACTIVE SYSTEMS</h5>
                <p>Engineering low-latency, high-fidelity interfaces and custom design systems. Specialized in WebGL, real-time visualization, and reactive architecture.</p>
              </div>
              <div class="service-block">
                <h5>CINEMATIC TECH PROPS</h5>
                <p>Design and fabrication of period-accurate and retro-futuristic interfaces for film. Technically correct terminal systems and hardware peripherals.</p>
              </div>
              <div class="service-block">
                <h5>AEROSPACE & AVIONICS</h5>
                <p>Specialized aviation solutions including advanced flight training systems, certification protocols, and custom aircraft electronics integration.</p>
              </div>
              <div class="service-block">
                <h5>PRECISION ENGINEERING</h5>
                <p>Industrial design and manufacturing of custom equipment. Expertise in composite materials, high-precision CNC machining, and industrial 3D printing.</p>
              </div>
              <div class="service-block">
                <h5>STRATEGIC VENTURE</h5>
                <p>Strategic guidance and seed-stage funding for technical startups. Providing the architectural and capital foundation for ambitious embryonic projects.</p>
              </div>
              <div class="service-block">
                <h5>INFRASTRUCTURE</h5>
                <p>Design and deployment of resilient, permanent digital infrastructure. Focus on secure, high-availability architecture for modern enterprise assets.</p>
              </div>
            </div>
          </div>

          <div v-else-if="activeTabId === 'collabs'" key="collabs" class="tab-content">
            <h4>COLLABS</h4>
            <p>Past and present collaborations and partnerships.</p>
            <div class="logo-grid">
              <div v-for="collab in collabs" :key="collab.name" class="logo-item">
                <div class="logo-plaque">
                  <img :src="collab.logo" :alt="collab.name" />
                  <div class="logo-overlay">
                    <span class="overlay-name">{{ collab.name }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>

<style scoped>
.section-content h3 {
    margin-top: 0;
    color: var(--color-accent);
    border-bottom: 1px solid rgba(64, 224, 208, 0.3);
    display: inline-block;
    padding-bottom: 5px;
    margin-bottom: 20px;
    font-size: 1.2rem;
    letter-spacing: 1px;
}

.business-layout {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 40px;
    min-height: 300px;
}

/* Left Menu */
.business-menu {
    display: flex;
    flex-direction: column;
    gap: 15px;
    border-right: 1px solid rgba(255,255,255,0.1);
    padding-right: 20px;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: bold;
    color: #666;
    transition: all 0.2s ease;
}

.menu-item:hover {
    color: #fff;
}

.menu-item.active {
    color: var(--color-accent);
}

.menu-item .indicator {
    opacity: 0;
    transition: opacity 0.2s ease;
}

.menu-item.active .indicator {
    opacity: 1;
}

/* Right Viewport */
.business-viewport {
    flex: 1;
    display: grid;
    grid-template-areas: "stack";
    position: relative;
}

.tab-content {
    grid-area: stack;
    width: 100%; /* Ensure full width */
}

.tab-content h4 {
    margin-top: 0;
    font-size: 1.4rem;
    color: #fff;
    margin-bottom: 20px;
    letter-spacing: 1px;
}

.tab-content p {
    font-size: 1.1rem;
    line-height: 1.6;
    color: rgba(255,255,255,0.8);
    margin-bottom: 20px;
}

.service-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-top: 10px;
}

.service-block h5 {
    color: var(--color-accent);
    margin: 0 0 10px 0;
    font-size: 1.1rem;
    font-weight: bold;
    letter-spacing: 1px;
}

.service-block p {
    font-size: 1.05rem;
    line-height: 1.6;
    margin: 0;
    color: rgba(255, 255, 255, 0.7);
}

.logo-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 30px;
    margin-top: 10px;
}

.logo-item {
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-plaque {
    aspect-ratio: 1/1; /* STRICT SQUARE */
    position: relative;
    overflow: hidden; /* Contain the name slide */
    width: 100%;
    max-width: 160px; /* Preserve reasonable sizing */
    
    /* Physical Plaque Styling moved here */
    background: rgba(255, 255, 255, 0.02);
    border: 5px solid rgba(255, 255, 255, 0.05);
    border-radius: 20px;
    box-shadow: 
        inset 1px 1px 2px rgba(255, 255, 255, 0.1),
        inset -1px -1px 3px rgba(0, 0, 0, 0.6),
        0 4px 10px rgba(0, 0, 0, 0.4);
    
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.95);
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    transform: translateY(100%);
    transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 2;
    pointer-events: none;
}

.logo-plaque:hover .logo-overlay {
    transform: translateY(0);
}

.overlay-name {
    color: var(--color-accent);
    font-weight: 900;
    font-size: 0.8rem;
    text-align: center;
    letter-spacing: 1px;
    padding: 10px;
    text-transform: uppercase;
}

.logo-plaque img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    filter: contrast(1.05) brightness(1.05);
    mix-blend-mode: screen;
    opacity: 0.85;
}

.animate-in {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}



.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.4s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

@media (max-width: 900px) {
    .section-content h3 {
        font-size: 1.1rem;
        margin-bottom: 15px;
    }

    .business-layout {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .business-menu {
        flex-direction: row;
        border-right: none;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding-right: 0;
        padding-bottom: 10px;
        overflow-x: auto;
        gap: 10px;
        scrollbar-width: none;
    }

    .business-menu::-webkit-scrollbar {
        display: none;
    }

    .menu-item {
        font-size: 0.8rem;
        padding: 5px;
        flex-shrink: 0;
    }

    .tab-content h4 {
        font-size: 1.1rem;
        margin-bottom: 10px;
    }

    .tab-content p {
        font-size: 0.95rem;
    }

    .service-grid {
        grid-template-columns: 1fr !important;
        gap: 15px;
    }

    .service-block h5 {
        font-size: 0.95rem;
    }

    .service-block p {
        font-size: 0.9rem;
    }

    .logo-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
}
</style>
