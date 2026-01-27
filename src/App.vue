<script setup>
import GridOverlay from './components/GridOverlay.vue'
import HeroDisplay from './components/HeroDisplay.vue'
import ContentCommander from './components/ContentCommander/ContentCommander.vue'
import SoundManager from './sfx/SoundManager'
import BootLoader from './components/BootLoader.vue'
import VfdDisplay from './components/VfdDisplay.vue'
import CrtControls from './components/CrtControls.vue'
import TrackerOverlay from './components/TrackerOverlay.vue'
import BezelReflection from './components/BezelReflection.vue'
import GlitchEffects from './components/GlitchEffects.vue'
import { ref, reactive, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { chatStore } from './store/chatStore';
import { keyboardStore } from './store/keyboardStore';
import VirtualKeyboard from './components/VirtualKeyboard.vue';
import MatrixOverlay from './components/MatrixOverlay.vue';


let cursorInterval = null;
let clockInterval = null;
const currentTime = ref('');

const updateTime = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('en-US', { hour12: false });
};


const windowWidth = ref(window.innerWidth)
const windowHeight = ref(window.innerHeight)

// Initialize to center to avoid initial jump/offset
const mouseX = ref(window.innerWidth / 2)
const mouseY = ref(window.innerHeight / 2)

const isCrawler = () => {
  const ua = navigator.userAgent.toLowerCase();
  const isBotUserAgent = /googlebot|bingbot|yandex|baiduspider|twitterbot|facebookexternalhit|rogerbot|linkedinbot|embedly|quora link preview|showyoubot|outbrain|pinterest\/0\.|pinterestbot|slackbot|vkShare|W3C_Validator|whatsapp|duckduckbot|lighthouse|google page speed|insights|gtmetrix|pingdom|screaming frog|google-inspectiontool/.test(ua);
  
  // Check for Headless browsers (often used by bots/crawlers)
  const isHeadless = navigator.webdriver || !!window.navigator.webdriver;

  return isBotUserAgent || isHeadless;
};

// Initialize isBooted to true if crawler, effectively skipping the intro
const isBot = isCrawler();
const isBooted = ref(isBot);
const screenRect = ref(null); // Cache for performance
const activeTabIndex = ref(0);
const lastActiveContentTab = ref(1); // Default to BUSINESS
const isScrollingManually = ref(false);

// Marquee Selection State
const isSelecting = ref(false);
const startSelectionX = ref(0);
const startSelectionY = ref(0);
const currentSelectionX = ref(0);
const currentSelectionY = ref(0);
const isMobile = computed(() => windowWidth.value <= 900);

const tabs = [
  { id: 'home', name: 'HOME' },
  { id: 'what', name: 'WHAT' },
  { id: 'why', name: 'WHY' },
  { id: 'blog', name: 'BLOG' },
  { id: 'guestbook', name: 'GUESTBOOK' },
  { id: 'chat', name: 'CHAT' }
];

const updateScreenRect = () => {
    const el = document.querySelector('.app-container');
    if (el) {
        void el.offsetHeight;
        screenRect.value = el.getBoundingClientRect();
    }
};

const handleMouseMove = (e) => {
  mouseX.value = e.clientX
  mouseY.value = e.clientY
  
  // Update rect if missing (lazy init)
  if (!screenRect.value) updateScreenRect();
}

const handleCombinedMouseMove = (e) => {
    handleMouseMove(e);
    if (isSelecting.value) updateSelection(e);
};

const handleGlobalMouseDown = (e) => {
    updateLockStates(e);
    handleGlobalClick(e);
    startSelection(e);
};



const handleResize = () => {
    windowWidth.value = window.innerWidth
    windowHeight.value = window.innerHeight
    updateScreenRect();
}


const isCapsLock = ref(false);
const isHddActive = ref(false);
const isPowerOn = ref(true);

// Matrix Easter Egg State
const showMatrixAnim = ref(false);
let matrixKeyBuffer = '';

const updateLockStates = (e) => {
  if (e.getModifierState) {
    isCapsLock.value = e.getModifierState('CapsLock');
  }
};

const simulateHddActivity = () => {
    // Random bursts of activity
    if (Math.random() > 0.7) {
        isHddActive.value = true;
        SoundManager.playHddSound();
        setTimeout(() => { isHddActive.value = false }, 50 + Math.random() * 100);
    }
    // Schedule next check
    setTimeout(simulateHddActivity, 50 + Math.random() * 200);
};

const isUpHeld = ref(false);

const handleGlobalKeyup = (e) => {
  updateLockStates(e);
  if (e.key === 'ArrowUp' || e.key === 'Up') {
      isUpHeld.value = false;
  }
};

onMounted(() => {
  window.addEventListener('mousemove', handleCombinedMouseMove);
  window.addEventListener('mouseup', endSelection);
  window.addEventListener('resize', handleResize)

  // Initial Power-On Animation Cleanup
  if (showPowerOnAnim.value) {
      setTimeout(() => {
          showPowerOnAnim.value = false;
      }, 1000);
  }
  
  // Robust Screen Rect Init
  nextTick(() => {
      updateScreenRect();
      // Retry for layout shifts
      setTimeout(updateScreenRect, 100);
      setTimeout(updateScreenRect, 500);
      
      // ResizeObserver for robustness
      const el = document.querySelector('.app-container');
      if (el) {
          const ro = new ResizeObserver(updateScreenRect);
          ro.observe(el);
      }
  });

  // Monitor Lock States (keydown/up for CapsLock)
  window.addEventListener('keydown', updateLockStates);
  window.addEventListener('keyup', handleGlobalKeyup);
  window.addEventListener('mousedown', handleGlobalMouseDown);
  
  simulateHddActivity();
  document.addEventListener('mouseover', handleGlobalHover);
  window.addEventListener('keydown', handleGlobalKeydown, { capture: true }); 
  window.addEventListener('popstate', handlePopState);
  
  // Initial Route Check
  updateIndexFromUrl();

  updateIndexFromUrl();
})

const startSelection = (e) => {
    if (!isBooted.value) return;
    // Don't start selection if clicking an input or button
    if (e.target.matches('button, a, input, textarea, [role="button"]')) return;

    isSelecting.value = true;
    updateScreenRect(); // Refresh rect before calculation
    
    // Coordinates relative to the screen (.app-container)
    startSelectionX.value = e.clientX - (screenRect.value?.left || 0);
    startSelectionY.value = e.clientY - (screenRect.value?.top || 0);
    currentSelectionX.value = startSelectionX.value;
    currentSelectionY.value = startSelectionY.value;
};

const updateSelection = (e) => {
    if (!isSelecting.value || !screenRect.value) return;
    
    currentSelectionX.value = e.clientX - screenRect.value.left;
    currentSelectionY.value = e.clientY - screenRect.value.top;
};

const endSelection = () => {
    if (isSelecting.value) isSelecting.value = false;
};

const selectionBoxStyle = computed(() => {
    if (!isSelecting.value) return { display: 'none' };
    
    const left = Math.min(startSelectionX.value, currentSelectionX.value);
    const top = Math.min(startSelectionY.value, currentSelectionY.value);
    const width = Math.abs(currentSelectionX.value - startSelectionX.value);
    const height = Math.abs(currentSelectionY.value - startSelectionY.value);
    
    return {
        left: `${left}px`,
        top: `${top}px`,
        width: `${width}px`,
        height: `${height}px`
    };
});

const handlePopState = (e) => {
    updateIndexFromUrl();
};

const handleGlobalHover = (e) => {
  // Check if target is interactive
  if (e.target.matches('button, a, input, [role="button"]')) {
     SoundManager.playHoverSound();
  }
}

const handleGlobalClick = (e) => {
    // Prevent focus stealing if clicking interactive elements
    if (e.target.matches('button, a, input, textarea, [role="button"], select, label, .interactive-star, .esc-label')) return;

    // Delay slightly to let standard focus change finish, then steal it back
    setTimeout(() => {
        // 1. Dial-up Popup (BootLoader)
        const dialupInput = document.querySelector('.popup-overlay .input-group input');
        if (dialupInput) {
            dialupInput.focus();
            return;
        }

        // 2. Guestbook Modal
        const guestbookInput = document.querySelector('.modal-content .form-group input');
        if (guestbookInput) {
            guestbookInput.focus();
            return;
        }

        // 3. Chat Focus Lock
        if (activeTabIndex.value === 4) { // Chat index
            const chatInput = document.querySelector('.irc-input-row input');
            if (chatInput && document.activeElement !== chatInput) {
                // Don't steal if user purposefully clicked another input (unlikely due to matches check above)
                if (!document.activeElement.matches('input, textarea, button, a')) {
                    chatInput.focus();
                }
            }
        }
    }, 10);
}

// Power Logic
const showPowerOffAnim = ref(false);
const showPowerOnAnim = ref(!isBot); // Play anim on load/refresh (unless bot)

watch(isPowerOn, (newVal) => {
    if (!newVal) {
        // Power Off Trigger
        showPowerOffAnim.value = true;
        
        setTimeout(() => {
            // Complete Power Down (Reset State)
            isBooted.value = false;
            vfdMode.value = 'off';
            vfdBootState.value = 'complete'; // Ensure VFD can show Standby
            SoundManager.stop();
            showPowerOffAnim.value = false;
        }, 400);
    } else {
        // Power On Trigger
        showPowerOnAnim.value = true;
        SoundManager.resume();
        
        // We do NOTHING here except set state variables if needed.
        // The v-if="isPowerOn" in template will re-mount the application.
        // The BootLoader component will mount, see isBooted=false, and start the sequence automatically.
        
        // Reset VFD to loading state for the boot sequence
        vfdBootState.value = 'loading';
        vfdMode.value = 'spectrum'; // (Or off until bootloader grabs it)

        // Clear animation after it finishes
        setTimeout(() => {
            showPowerOnAnim.value = false;
        }, 1000); // 1s matches animation approx
    }
});

const handleBootStart = async () => {
  // 1. Reveal Content IMMEDIATELY (Safari Fix)
  isBooted.value = true;
  vfdBootState.value = 'complete'; 
  
  // 2. Initialize Audio in background (don't await)
  try {
      if (!SoundManager.initialized) SoundManager.init();
      SoundManager.resume().then(() => {
          SoundManager.playBootSequence();
          SoundManager.loadVisualizer('/music/impulse.s3m');
          setTimeout(() => {
            SoundManager.playTrackerMusic('/music/impulse.s3m');
          }, 3800); 
      });
  } catch (e) {
      console.warn('Audio auto-start blocked by browser');
  }
  
  // 3. Trigger Post-Boot SONY Logo
  vfdMode.value = 'logo';
  setTimeout(() => {
        vfdMode.value = 'spectrum';
  }, 2100);
  
  // 4. Update screenRect after layout settles (Safari fix)
  setTimeout(() => {
      updateScreenRect();
  }, 100);
}

const handleBootSkip = async () => {
    // 1. Reveal Content IMMEDIATELY (Safari Fix)
    isBooted.value = true;
    vfdBootState.value = 'complete'; 
    vfdMode.value = 'spectrum';

    // 2. Initialize Audio in background (don't await)
    try {
        if (!SoundManager.initialized) SoundManager.init();
        SoundManager.resume().then(() => {
            SoundManager.loadVisualizer('/music/impulse.s3m');
            SoundManager.playTrackerMusic('/music/impulse.s3m');
        });
    } catch (e) {
        console.warn('Audio skip blocked by browser');
    }
    
    // 3. Initialize Chat if needed
    if (!chatStore.nickname || chatStore.nickname.trim() === '') {
        const rand = Math.floor(1000 + Math.random() * 9000);
        chatStore.nickname = `guest-${rand}`;
    }
    chatStore.isConnected = true;
    chatStore.showPopup = false;
    await chatStore.init();
    
    // 4. Update screenRect after layout settles (Safari fix)
    setTimeout(() => {
        updateScreenRect();
    }, 100);
};


const vfdMode = ref('spectrum'); // Start with canvas for loading bar
const vfdKnobInfo = ref({ label: '', value: '' });
// Boot items
const vfdBootState = ref(isBot ? 'complete' : 'loading'); // 'loading', 'ready', 'complete', 'connecting'
const vfdStatusText = ref('wait');
const bootProgress = ref(0);

// VFD Label Glow - based on VFD activity
const vfdLabelGlow = computed(() => {
  if (vfdMode.value === 'spectrum' || vfdBootState.value === 'loading') {
    return '0.35'; // High glow during spectrum/loading
  } else if (vfdMode.value === 'logo' || vfdMode.value === 'knob') {
    return '0.20'; // Medium glow
  }
  return '0.0'; // No glow when off
});

let previousVfdMode = 'spectrum';

onUnmounted(() => {
  window.removeEventListener('mousemove', handleCombinedMouseMove);
  window.removeEventListener('mouseup', endSelection);
  window.removeEventListener('mousedown', handleGlobalMouseDown);

  window.removeEventListener('resize', handleResize);
  document.removeEventListener('mouseover', handleGlobalHover);
  window.removeEventListener('keydown', handleGlobalKeydown, { capture: true });
  
  // clearInterval(cursorInterval) // Removed legacy cleanup
  if (clockInterval) clearInterval(clockInterval);
})

const handleGlobalKeydown = (e) => {
  const isEscape = e.key === 'Escape' || e.key === 'Esc';
  const isUpKey = e.key === 'ArrowUp' || e.key === 'Up';
  
  if (isUpKey) isUpHeld.value = true;

  // 1A. Matrix Easter Egg Detection (GOD)
  // Only trigger if on Hero Section (activeTabIndex === 0)
  if (activeTabIndex.value === 0 && !showMatrixAnim.value) {
      if (/^[a-zA-Z]$/.test(e.key)) {
          matrixKeyBuffer += e.key;
          if (matrixKeyBuffer.length > 20) matrixKeyBuffer = matrixKeyBuffer.slice(-20);
          
          if (matrixKeyBuffer.endsWith('GOD')) {
              showMatrixAnim.value = true;
                SoundManager.playGlitchSound(); // Nice audio feedback
              setTimeout(() => {
                  showMatrixAnim.value = false;
                  matrixKeyBuffer = ''; // Reset
              }, 3000);
          }
      }
  }

  // 1. Check if we should block navigation (Easter Egg in progress)
  // If Up is held AND the current key is NOT the Up key, we block navigation
  if (isUpHeld.value && !isUpKey) {
      const navKeys = ['ArrowRight', 'ArrowLeft', 'ArrowDown', 'Tab', 'Enter', 'Right', 'Left', 'Down'];
      if (navKeys.includes(e.key)) {
          // Prevent browser behavior like scrolling or tab switching
          e.preventDefault();
          // We return here to skip the tab selection logic below in this function,
          // but we DON'T stop propagation so HeroDisplay.vue still sees the key.
          return;
      }
  }

  // 2. Handle Skip/Reboot early (Safari Fix)
  if (isEscape) {
      // Priority 0: Close Virtual Keyboard if open
      if (keyboardStore.isVisible.value) {
          keyboardStore.close();
          e.preventDefault();
          return;
      }

      if (!isBooted.value) {
          // If NOT booted, Escape ALWAYS skips the BIOS/Dialup sequence
          e.preventDefault();
          e.stopPropagation();
          setTimeout(() => handleBootSkip(), 0);
          return;
      } else {
          // IF booted, only allow Escape reboot if NO modal/popup is open
          const hasOverlay = document.querySelector('.modal-overlay, .popup-overlay');
          if (hasOverlay) return; // Let modals/popups handle their own Escape

          window.location.reload();
      }
      return;
  }

  if (!isBooted.value) return;

  // 3. Ignore if typing in an input or textarea
  const target = e.target;
  const isInput = target.matches('input, textarea, [contenteditable="true"]');
  if (isInput) {
      // Allow navigation keys to pass through IF they are at the boundaries
      // This lets users move the cursor within the chat/guestbook but still "pop out" to next tabs
      const isNavKey = ['ArrowRight', 'ArrowLeft', 'Tab', 'Right', 'Left'].includes(e.key);
      
      if (isNavKey) {
          if (e.key === 'Tab') {
              // Tab always navigates
          } else if (e.key === 'ArrowLeft' || e.key === 'Left') {
              // Only navigate if cursor is at the very start
              if (target.selectionStart !== 0 || target.selectionEnd !== 0) return;
          } else if (e.key === 'ArrowRight' || e.key === 'Right') {
              // Only navigate if cursor is at the very end
              const valLen = target.value ? target.value.length : 0;
              if (target.selectionStart !== valLen) return;
          }
      } else {
          // Block all other keys (typing) from triggering global site actions
          return;
      }
  }

  // Check if we are currently at the top (Hero section)
  const scrollContainer = document.querySelector('.scroll-content');
  if (!scrollContainer) return;

  // 4. Block Global Nav if a Modal/Popup is open (e.g. Guestbook, Alert, BootLoader)
  if (document.querySelector('.modal-overlay, .popup-overlay')) {
      return;
  }

  const isAtTop = scrollContainer.scrollTop < window.innerHeight / 2;

  // 2. Tab Navigation (Centralized)
  if (e.key === 'ArrowRight' || e.key === 'Right' || e.key === 'Tab') {
      e.preventDefault();
      const nextIndex = (activeTabIndex.value + 1) % tabs.length;
      handleTabSelect(nextIndex);
      SoundManager.playTypingSound();
  } else if (e.key === 'ArrowLeft' || e.key === 'Left') {
      e.preventDefault();
      const prevIndex = (activeTabIndex.value - 1 + tabs.length) % tabs.length;
      handleTabSelect(prevIndex);
      SoundManager.playTypingSound();
  }

  if (isAtTop && (e.key === 'ArrowDown' || e.key === 'Down' || e.key === 'Enter')) {
      // Move to the last active content tab (or Business)
      if (activeTabIndex.value === 0) {
          handleTabSelect(lastActiveContentTab.value);
      }
  }
}

// 4. Robust Scroll Listener Attachment
watch(isBooted, (booted) => {
    if (booted) {
        nextTick(() => {
            const scrollContainer = document.querySelector('.scroll-content');
            if (scrollContainer) {
                scrollContainer.addEventListener('scrollend', () => {
                    isScrollingManually.value = false;
                });

                if (activeTabIndex.value > 0) {
                    // Jump to content immediately after boot if we started on a deep link
                    scrollToContent('auto');
                }
            }
        });
    }
});

const handleScroll = (e) => {
    // If we just clicked a tab, ignore the scroll events triggered by that smooth scroll
    if (isScrollingManually.value) return;

    const scrollTop = e.target.scrollTop;
    const heroThreshold = window.innerHeight * 0.15;
    const contentThreshold = window.innerHeight * 0.55;
    
    let nextIndex = activeTabIndex.value;
    if (scrollTop < heroThreshold) {
        nextIndex = 0;
    } else if (scrollTop >= contentThreshold) {
        if (activeTabIndex.value === 0) {
            nextIndex = lastActiveContentTab.value;
        }
    }

    if (nextIndex !== activeTabIndex.value) {
        activeTabIndex.value = nextIndex;
        updateUrlFromIndex(nextIndex);
        updateMetadata(nextIndex);
    }
}

const metadataMap = {
  home: { title: "Ownedge | Independent by Design", description: "Defying the establishment. A digital window for independent creators and builders." },
  what: { title: "Ownedge | What We Do", description: "Exploring the boundaries of digital products, strategy, and engineering." },
  why: { title: "Ownedge | Why We Exist", description: "The Ownedge manifesto: our vision for a more intentional, independent digital future." },
  blog: { title: "Ownedge | Transmissions", description: "Updates, thoughts, and logs from the console." },
  guestbook: { title: "Ownedge | Leave Your Mark", description: "Sign the guestbook and join the lineage of terminal users." },
  chat: { title: "Ownedge | Terminal Cluster", description: "Communicate in real-time with other nodes connected to the Ownedge cluster." }
};

const updateMetadata = (index) => {
    const tab = tabs[index];
    const data = metadataMap[tab.id];
    if (data) {
        document.title = data.title;
        
        let descriptionTag = document.querySelector('meta[name="description"]');
        if (!descriptionTag) {
            descriptionTag = document.createElement('meta');
            descriptionTag.setAttribute('name', 'description');
            document.head.appendChild(descriptionTag);
        }
        descriptionTag.setAttribute('content', data.description);

        // Update Open Graph tags
        const paths = ['/', '/what', '/why', '/guestbook', '/chat'];
        const path = paths[index] || '/';
        const url = `https://ownedge.com${path}`;

        const updateMetaTag = (property, content) => {
            let tag = document.querySelector(`meta[property="${property}"]`);
            if (!tag) {
                tag = document.createElement('meta');
                tag.setAttribute('property', property);
                document.head.appendChild(tag);
            }
            tag.setAttribute('content', content);
        };

        updateMetaTag('og:title', data.title);
        updateMetaTag('og:description', data.description);
        updateMetaTag('og:url', url);

        // Update Twitter tags
        updateMetaTag('twitter:title', data.title);
        updateMetaTag('twitter:description', data.description);
        updateMetaTag('twitter:url', url);
    }
};

const updateUrlFromIndex = (index) => {
    const tabId = tabs[index]?.id;
    if (!tabId) return;
    
    // Construct path: home -> /, others -> /id/
    const path = tabId === 'home' ? '/' : `/${tabId}/`;
    
    if (window.location.pathname !== path) {
        history.pushState({ index }, '', path);
    }
};

const initialBlogPostId = ref(null);
import { provide } from 'vue';
provide('initialBlogPostId', initialBlogPostId);

const updateIndexFromUrl = () => {
    // Normalize path by removing trailing slash (e.g. /chat/ -> /chat)
    let path = window.location.pathname;
    if (path.length > 1 && path.endsWith('/')) {
        path = path.slice(0, -1);
    }
    
    // Parse ID from path (e.g. /what -> what)
    // path is '' or '/' or '/what'
    const pathId = path === '/' || path === '' ? 'home' : path.substring(1); // remove leading slash
    
    // 1. Check if path matches a main tab ID
    const tabIndex = tabs.findIndex(t => t.id === pathId);
    if (tabIndex !== -1) {
        if (tabIndex !== activeTabIndex.value) {
            handleTabSelect(tabIndex);
        }
        return;
    }

    // 2. Check for Blog Deep Link (e.g. /blog/my-post)
    if (path.startsWith('/blog/')) {
        const parts = path.split('/');
        // path is like /blog/my-post
        // parts: ['', 'blog', 'my-post']
        if (parts.length >= 3) {
            const blogId = parts[2];
            if (blogId) {
                initialBlogPostId.value = blogId; // Provide to BlogSection
                
                // Find index of 'blog' tab strictly
                const blogTabIndex = tabs.findIndex(t => t.id === 'blog');
                if (blogTabIndex !== -1) handleTabSelect(blogTabIndex);
                return;
            }
        }
    }
};

const scrollToContent = (behavior = 'smooth') => {
    const scrollContainer = document.querySelector('.scroll-content');
    if (!scrollContainer) return;
    
    const sections = document.querySelectorAll('.page-section');
    if (sections.length > 1) {
        const targetTop = sections[1].offsetTop;
        // Optimization: If we are already at the target area, don't trigger a scroll.
        // This prevents Safari from flickering/jumping when switching tabs inside ContentCommander.
        if (Math.abs(scrollContainer.scrollTop - targetTop) < 30) return;

        scrollContainer.scrollTo({ top: targetTop, behavior });
    }
};

const handleTabSelect = (index) => {
    // CRITICAL: Set lock BEFORE updating index to stop observer immediately
    isScrollingManually.value = true;
    
    // Close keyboard if open (fixes layout shift issues on mobile)
    if (keyboardStore.isVisible.value) {
        keyboardStore.close();
    }
    
    activeTabIndex.value = index;
    
    // Update lastActiveContentTab if not HOME (0)
    if (index > 0) {
        lastActiveContentTab.value = index;
    }

    updateUrlFromIndex(index);
    updateMetadata(index);

    const finishScroll = () => {
        // Fallback timeout: Increased to 2000ms to cover long wrap-around scrolls (Chat -> Home)
        setTimeout(() => { isScrollingManually.value = false; }, 2000);
    };

    if (tabs[index].id === 'home') {
        const scrollContainer = document.querySelector('.scroll-content');
        if (scrollContainer) {
            scrollContainer.scrollTo({ top: 0, behavior: 'smooth' });
            finishScroll();
        }
    } else {
        // Only scroll to the content section if we aren't already there.
        // This is key for Safari stability.
        scrollToContent();
        
        // Mobile Layout Shift Fix:
        // If on mobile, the layout (Hero height) might animate/change if keyboard closes.
        // We must re-adjust scroll AFTER the animation settles.
        if (isMobile.value) {
             setTimeout(() => {
                 scrollToContent();
                 finishScroll();
             }, 350); // > 200ms transition + buffer
        } else {
             finishScroll();
        }
    }
};

const resetToDefaults = () => {
    brightness.value = 1;
    contrast.value = 1;
    SoundManager.setMasterVolume(0.9);
    SoundManager.playTypingSound();
};







// --- Power Controls Logic ---
import { SYSTEM_CONFIG } from './config';

const SETTINGS_KEY = 'crt_settings';
const NICKNAME_KEY = 'chat_nickname';

const loadSettings = () => {
    try {
        const saved = localStorage.getItem(SETTINGS_KEY);
        return saved ? JSON.parse(saved) : null;
    } catch (e) {
        console.warn('Failed to load settings', e);
        return null;
    }
};

const loadNickname = () => {
    try {
        const saved = localStorage.getItem(NICKNAME_KEY);
        return saved || '';
    } catch (e) {
        return '';
    }
};

// 1. Load nickname FIRST and sync with store immediately (before other refs)
const initialNickname = loadNickname();
chatStore.nickname = initialNickname;

const savedSettings = loadSettings();

const volume = ref(savedSettings?.volume ?? SYSTEM_CONFIG.AUDIO.MASTER_VOL);
const brightness = ref(savedSettings?.brightness ?? SYSTEM_CONFIG.VISUALS.BRIGHTNESS_DEFAULT);
const contrast = ref(savedSettings?.contrast ?? SYSTEM_CONFIG.VISUALS.CONTRAST_DEFAULT);
const isTurbo = ref(savedSettings?.isTurbo ?? SYSTEM_CONFIG.VISUALS.TURBO_DEFAULT);

// Sync initial volume
SoundManager.setMasterVolume(volume.value);

const saveSettings = () => {
    const settings = {
        volume: volume.value,
        brightness: brightness.value,
        contrast: contrast.value,
        isTurbo: isTurbo.value
    };
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
};

const saveNickname = (nick) => {
    localStorage.setItem(NICKNAME_KEY, nick);
};

// Auto-save on change
watch([volume, brightness, contrast, isTurbo], saveSettings);
watch(() => chatStore.nickname, (newNick) => {
    saveNickname(newNick);
});

// Generic Knob State handled in CrtControls
// VFD Updates
const handleKnobStart = ({ type, value }) => {
    // Switch VFD to Knob Mode
    if (vfdMode.value !== 'knob') {
        previousVfdMode = vfdMode.value;
        vfdMode.value = 'knob';
    }
    updateKnobText(type, value);
};

const handleKnobChange = ({ type, value }) => {
    // Update local state is handled by v-model events automatically for volume/brt/con
    // But we need to update VFD text and side effects
    
    if (type === 'vol') {
        SoundManager.setMasterVolume(value); // Side effect
    }
    updateKnobText(type, value);
};

const handleKnobEnd = () => {
    // Restore VFD
    if (vfdMode.value === 'knob') {
        vfdMode.value = previousVfdMode;
    }
};

const updateKnobText = (type, val) => {
    let label = '';
    let pct = 0;
    
    if (type === 'vol') {
        label = 'VOLUME';
        pct = Math.round(val * 100);
    } else if (type === 'brt') {
        label = 'BRGHTNSS';
        // Map 0.5-1.5 range to 0-100%
        pct = Math.round((val - 0.5) * 100);
    } else if (type === 'con') {
        label = 'CONTRST';
        // Map 0.5-1.5 range to 0-100%
        pct = Math.round((val - 0.5) * 100);
    }
    // Clamp to 0-100 just in case
    pct = Math.max(0, Math.min(100, pct));
    
    vfdKnobInfo.value = { label, value: `${pct}%` };
};

const scanlineColor = `hsl(188, 40%, 9%)`;
const vfdBgColor = `hsl(188, 42%, 7%)`;


const isGlitching = ref(false);

const currentFilter = computed(() => {
    // If animating power-on, let CSS Keyframes control the filter completely
    if (showPowerOnAnim.value) return null;

    const b = brightness.value * 1.1;
    const c = contrast.value;
    const base = `brightness(${b}) contrast(${c})`;
    
    // Optimization: Only apply heavy SVG filters when actively glitching
    if (isGlitching.value) {
        return `${base} url(#spherical-warp) url(#wave-glitch)`;
    }
    return base;
});

const handleGlitchState = (isActive) => {
    isGlitching.value = isActive;
};

// --- AUTO PERFORMANCE (Turbo Mode) ---
const fps = ref(60);
const fpsHistory = ref([]);
let frameCount = 0;
let lastTime = performance.now();
let lastSwitchTime = 0; // Cooldown to prevent oscillation
let perfReqId = null;

const trackAndManagePerformance = () => {
    const now = performance.now();
    frameCount++;
    
    // Update FPS once per second
    if (now - lastTime >= 1000) {
        fps.value = frameCount;
        frameCount = 0;
        lastTime = now;
        
        // Auto-Turbo Logic
        // Only run if we haven't switched recently (5s cooldown)
        if (now - lastSwitchTime > 5000) {
            if (fps.value < 30 && !isTurbo.value) {
                // Performance Critical: Enable Turbo
                isTurbo.value = true;
                lastSwitchTime = now;
                console.log("Auto-Turbo: ENABLED (Low FPS)");
            } else if (fps.value >= 58 && isTurbo.value) {
                // Performance Good: Disable Turbo (Try for high fidelity)
                isTurbo.value = false;
                lastSwitchTime = now;
                console.log("Auto-Turbo: DISABLED (High FPS)");
            }
        }
    }
    
    perfReqId = requestAnimationFrame(trackAndManagePerformance);
};

onMounted(() => {
    trackAndManagePerformance();
});

onUnmounted(() => {
    if (perfReqId) cancelAnimationFrame(perfReqId);
});

</script>

<template>
  <div class="crt-wrapper">
        
    <!-- Fixed Status LEDs -->
    <!-- Extracted Controls (LEDs + Knobs) -->
    <CrtControls
        class="crt-controls"
        v-model:volume="volume"
        v-model:brightness="brightness"
        v-model:contrast="contrast"
        v-model:is-turbo="isTurbo"
        v-model:power-led="isPowerOn"
        :is-caps-lock="isCapsLock"
        :is-hdd-active="isHddActive"
        @knob-start="handleKnobStart"
        @knob-change="handleKnobChange"
        @knob-end="handleKnobEnd"
    />

    <!-- VFD Display (Replaces Logo) -->
    <!-- VFD Label -->
    <div class="vfd-label-box">
        <img src="./assets/ownedge-logo.png" class="bezel-logo" />
        <div class="vfd-label-text">
            <div class="vfd-label-line1">VF-D74Ø</div>
            <div class="vfd-label-line2">SUPER</div>
        </div>
    </div>
    
    <!-- VFD Display (Extracted to component) -->
    <VfdDisplay 
        class="vfd-display"
        :mode="vfdMode"
        :knob-info="vfdKnobInfo"
        :boot-state="vfdBootState"
        :boot-progress="bootProgress"
        :status-text="vfdStatusText"
        :scanline-color="vfdBgColor"
    />

    <div class="crt-screen">
      <!-- Apply 'crt-content' class for filter -->
      <div 
        class="app-container" 
        :class="{ 'power-off-anim': showPowerOffAnim, 'power-on-anim': showPowerOnAnim }" 
        :style="{ filter: currentFilter }"
        v-if="isPowerOn || showPowerOffAnim"
      >
        <!-- Fixed Background/Overlays -->
        <BootLoader 
          v-if="!isBot"
          :is-booted="isBooted" 
          @start="handleBootStart"
          @skip="handleBootSkip"
          @progress="(p) => { if (!isBooted) bootProgress = p }"
          @ready="() => { if (!isBooted) vfdBootState = 'ready' }"
          @connecting="() => { if (!isBooted) vfdBootState = 'connecting' }"
          @status-update="(s) => { if (!isBooted) vfdStatusText = s }"
        />

        <!-- Selection Marquee -->
        <div 
          v-if="isSelecting" 
          class="selection-marquee" 
          :style="selectionBoxStyle"
        ></div>

        <div class="fixed-background" @mousedown="startSelection">
            <GridOverlay v-if="!isTurbo" />
            <TrackerOverlay :clean-mode="isTurbo" />
        </div>

        <!-- Scrollable Content -->
        <div class="scroll-content" :class="{ 'keyboard-open': keyboardStore.isVisible.value && isMobile }" v-if="isBooted" @scroll.passive="handleScroll" @mousedown="startSelection">
          <section class="page-section hero-section" :style="heroStyle">
            <HeroDisplay />
          </section>
          
          <section class="page-section">
            <ContentCommander 
               :tabs="tabs" 
               :active-index="activeTabIndex" 
               @update:active-index="handleTabSelect"
               @reset-settings="resetToDefaults"
            />
          </section>
        </div>
        

        
        <MatrixOverlay :active="showMatrixAnim" />

        <!-- Fixed Foreground Overlays -->
        <div class="scanlines" :class="{ 'optimized': isTurbo }"></div>
        <div class="vignette"></div>

        <!-- Global Virtual Keyboard (Mobile Only) -->
        <VirtualKeyboard />
      </div>
    </div>
    
    <!-- Bezel Reflection Overlay (Tracker Text only) -->
    <TrackerOverlay 
      v-if="!isTurbo"
      class="bezel-reflection-text" 
      :class="{ 'fade-out': showPowerOffAnim }"
      style="z-index: 100; pointer-events: none;" 
      :reflection-only="true" 
      :screen-rect="screenRect" 
    />
    
    <!-- Bezel Glow Bars (Menu/Footer reflection) -->
    <BezelReflection v-if="isBooted && !isTurbo" :class="{ 'fade-out': showPowerOffAnim }" />

    <!-- Vintage Sony Sticker (Top Left) -->
    <div class="bezel-sticker">
        <img src="./assets/corner-sticker.png" />
        <div class="sticker-wear"></div>
    </div>
  </div>
    <GlitchEffects :active="!isBot && !isTurbo" @glitch-playing="handleGlitchState" />
</template>

<style scoped>

.fade-out {
    opacity: 0 !important;
    transition: opacity 0.2s ease-out;
}

/* CRT Wrapper (The Bezel/Room) */
.crt-wrapper {
  width: 100vw;
  height: 100vh;
  /* Use dvh for mobile browsers if supported */
  height: 100dvh; 
  background-color: #050505;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 43px 40px 83px 40px; /* Thicker chin */
  position: relative;
  overflow: hidden;
  user-select: none; /* Disable global text selection */
}

/* Allow selection in inputs/textareas */
.crt-wrapper input,
.crt-wrapper textarea,
.crt-wrapper [contenteditable="true"] {
    user-select: text;
}


.crt-wrapper::after {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 1;
  /* Scratches and wear marks */

  background-repeat: no-repeat, no-repeat, repeat, repeat, repeat, repeat, repeat, repeat;
  background-position: 95% 5%, 5% 95%, center, center, center, center, center, center;
  background-size: 400px 400px, 400px 400px, auto, auto, auto, auto, auto, auto;
  mix-blend-mode: color-dodge;
  /* Apply Mask to keep screen glass clean */
  -webkit-mask: url(#bezel-mask);
  mask: url(#bezel-mask);
}

/* Vintage Sticker Styles */
.bezel-sticker {
    position: absolute;
    top: 8px; /* Top bezel */
    left: 9px; /* Left bezel */
    width: 42px; /* Adjust size for Sony sticker */
    height: auto;
    z-index: 15;
    filter: contrast(0.8) brightness(1.7);
    transform: rotate(90deg);
    opacity: 0.36;
}

.bezel-sticker img {
    width: 100%;
    height: auto;
    display: block;
}

/* CRT Screen (The curvature and clipping) */
.crt-screen {
  width: 100%;
  height: 100%;
  max-width: 100%;
  max-height: 100%;
  border-radius: 40px; /* Slight curve at corners */
  position: relative;
  overflow: hidden;
  /* Strong inner shadow to simulate curved glass depth */
  box-shadow: 
    inset 0 0 80px rgba(0,0,0,0.9), 
    0 0 90px rgba(100, 100, 100, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
    /* Gradient Border Technique */
  border: 11px solid transparent;
  background-clip: padding-box, border-box;
  background-origin: padding-box, border-box;
  background-image: 
    linear-gradient(#000, #000), /* Inner Content (Black) */
    radial-gradient(circle at center, #333333 0%, #222222 90%); /* Border Gradient (Dark Inner -> Light Outer) */
}

.app-container {
  width: 101%;
  height: 101%;
  flex-shrink: 0; /* Prevent shrinking to fit */
  position: relative;
  background: radial-gradient(circle at center, #2f2f2f00 1%, #0e0e0e 90%);
  overflow: hidden; /* Container is fixed window */
  transform: translateZ(0); /* Force GPU */
}

/* Fixed Background Layer */
.fixed-background {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 10;
  pointer-events: none;
}

/* Scroll Content - This is the moving part */
.scroll-content {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  overflow-y: auto; /* Enable scrolling internal to this div */
  scroll-snap-type: y mandatory;
  scroll-behavior: smooth;
  z-index: 20; /* Above background but below overlays */
  
  /* Hide scrollbar */
  scrollbar-width: none;
}

.scroll-content::-webkit-scrollbar {
  display: none;
}

/* Page Sections */
.page-section {
  height: 100%; /* Fit the scroll-content container exactly */
  width: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  scroll-snap-align: start;
  position: relative;
}

.hero-section {
  height: calc(100% - 120px); /* Reveal next section peeking from bottom */
  transition: transform 0.1s ease-out;
}

/* Scanlines / Dot Matrix Mask */
.scanlines {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  /* Default High-Quality Radial (Turbo OFF) */
  background: radial-gradient(
    circle,
    transparent 2%,
    v-bind(scanlineColor) 95%
  );
  background-size: 1.5px 1.5px; /* Dot density */
  pointer-events: none;
  z-index: 50;
  opacity: 0.75;
}

.scanlines.optimized {
  /* Turbo ON: Linear Gradient */
  background: linear-gradient(
    to bottom,
    transparent 10%,
    v-bind(scanlineColor) 31%
  );
  background-size: 100% 1.2px;
  opacity: 0.55;
}

/* Vignette / Tube Curvature Simulation */
.vignette {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 60;
  box-shadow: inset 0 0 100px rgba(0,0,0,0.9);
  border-radius: 5px;
}

.selection-marquee {
    position: absolute;
    border: 1px solid rgba(72, 255, 237, 0.3);
    background-color: rgba(72, 255, 237, 0.05);
    z-index: 100;
    pointer-events: none; /* Let clicks pass through to content */
    box-shadow: 0 0 5px rgba(72, 255, 237, 0.1);
}
/* VFD Display Styling (Replaces Monitor Brand) */
.vfd-display {
    position: absolute;
    bottom: 25px; 
    left: 50%;
    transform: translateX(-50%);
    background-color: #050908;
    border: 1px solid #1a1a1a; /* Darker border */
}

/* CRT Power Off Animation */
.power-off-anim {
    animation: crt-power-off 0.4s ease-out forwards;
}

@keyframes crt-power-off {
    0% {
        opacity: 1;
        transform: scale(1);
        filter: brightness(1) contrast(1);
    }
    40% {
        opacity: 1;
        transform: scale(1, 0.02); /* Squash vertically to a line */
        filter: brightness(3) contrast(2); /* Flash bright */
    }
    100% {
        opacity: 0;
        transform: scale(0, 0); /* Shrink to center point */
        filter: brightness(0);
    }
}

/* CRT Power On Animation */
.power-on-anim {
    animation: crt-power-on 0.6s ease-out forwards;
}

@keyframes crt-power-on {
    0% {
        opacity: 0;
        transform: scale(0, 0); /* Start from nothing */
        filter: brightness(0);
    }
    20% {
        opacity: 1;
        transform: scale(1, 0.005); /* Thin Horizontal Line */
        filter: brightness(4) contrast(3); /* Super bright flash */
    }
    50% {
        opacity: 1;
        transform: scale(1, 1.1); /* Vertical Overshoot */
        filter: brightness(2) contrast(1.5);
    }
    70% {
        transform: scale(1, 0.95); /* Slight bounce back */
    }
    100% {
        opacity: 1;
        transform: scale(1, 1);
        filter: brightness(1) contrast(1);
    }
}

/* VFD Label Box */
.vfd-label-box {
    position: fixed;
    bottom: 1.8rem;
    left: calc(50% - 208px); /* Shifted to keep text in same absolute spot */
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0;
    pointer-events: none;
    z-index: 10001;
}

.bezel-logo {
    height: 34px;
    width: 37px;
    /* Invert because the logo is likely dark, then dim to match the worn #444 labels */
    filter: invert(1) brightness(0.4) contrast(1.2) opacity(0.4);
    mix-blend-mode: normal; /* Normal for better visibility on dark */
}

.vfd-label-text {
    transform: translateY(1px);
    display: flex;
    flex-direction: column;
}

.vfd-label-line1 {
    opacity: 0.8;
    font-family: 'Microgramma', 'Courier New', monospace;
    font-size: 0.61rem;
    color: #444;
    letter-spacing: 1px;
    transition: all 0.05s ease;
    text-align: left;
    line-height: 1.2;
    /* Radial gradient glow from VFD (right side) */
    background-image: radial-gradient(
        circle at 100% 50%, 
        color-mix(in srgb, #40e0d0, rgba(68,68,68,0.5) calc(100% - calc(v-bind(vfdLabelGlow) * 100%))) 0%, 
        #444 65%
    );
    background-clip: text;
    -webkit-background-clip: text;
}

.vfd-label-line2 {
    opacity: 0.8;
    font-family: 'Microgramma', 'Courier New', monospace;
    font-size: 0.6rem;
    color: #444;
    letter-spacing: 1px;
    transition: all 0.05s ease;
    text-align: left;
    margin-top: 1px;
    /* Radial gradient glow from VFD (right side) */
    background-image: radial-gradient(
        circle at 100% 50%, 
        color-mix(in srgb, #40e0d0, rgba(68,68,68,0.5) calc(100% - calc(v-bind(vfdLabelGlow) * 100%))) 0%, 
        #444 65%
    );
    background-clip: text;
    -webkit-background-clip: text;
}

/* Mobile Responsiveness */
@media screen and (max-width: 900px) {
    .crt-wrapper {
        padding: 4px; /* Thin frame/safe area */
        background: #000;
    }

    .crt-wrapper::after,
    .bezel-sticker,
    .bezel-reflection,
    .vfd-label-box {
        display: none !important;
    }

    /* Hide Hardware Components */
    .crt-controls,
    .vfd-display {
        display: none !important;
    }

    .crt-screen {
        border-radius: 5px; /* Subtle rounding */
        border: 0.5px solid #222; /* Define the container edge */
        box-shadow: none;
        width: 100%;
        height: 100%;
        /* No fixed 100vh here, parent wrapper controls size via padding */
    }

    .app-container {
        width: 100%;
        height: 100%;
        filter: brightness(v-bind(brightness*0.9)) contrast(v-bind(contrast)); /* Remove spherical-warp */
    }

    .scroll-content {
        height: 100%;
        /* Ensure snappy behavior */
        scroll-padding-top: 0; 
        transition: height 0.2s cubic-bezier(0.2, 0.8, 0.2, 1);
    }

    .scroll-content.keyboard-open {
        height: calc(100% - 240px);
    }

    .hero-section {
        /* Peek the menu bar (50px) at the bottom */
        height: calc(100% - 55px); 
        min-height: calc(100% - 55px);
    }
}
</style>