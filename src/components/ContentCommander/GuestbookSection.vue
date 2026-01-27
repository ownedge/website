<script setup>
import { ref, onMounted, computed, onUnmounted, nextTick, watch } from 'vue';
import SoundManager from '../../sfx/SoundManager';
import { chatStore } from '../../store/chatStore';
import { keyboardStore } from '../../store/keyboardStore';

const getFlagEmoji = (countryCode) => {
  if (!countryCode) return '🏴‍☠️'; 
  try {
      const codePoints = countryCode
        .toUpperCase()
        .split('')
        .map(char =>  127397 + char.charCodeAt());
      return String.fromCodePoint(...codePoints);
  } catch (e) {
      return '🏴‍☠️';
  }
};

const entries = ref([]);
const isModalOpen = ref(false);
const isSubmitting = ref(false);
const showSuccess = ref(false);
const isSigned = ref(false);

const messageInput = ref(null);

const newEntry = ref({
  name: '',
  message: '',
  rating: 5
});

const API_URL = import.meta.env.PROD 
  ? '/api/guestbook.php' 
  : '/api/guestbook.php'; // Local Vite proxy handles /api -> ownedge.com

const fetchEntries = async () => {
    try {
        const response = await fetch(API_URL);
        if (response.ok) {
            entries.value = await response.json();
            entries.value.reverse(); // Newest first
        }
    } catch (err) {
        console.error('Failed to fetch guestbook:', err);
    }
};

const checkIsSigned = () => {
    isSigned.value = document.cookie.split(';').some(c => c.trim().startsWith('guestbook_signed='));
};

const openModal = () => {
    if (isSigned.value) return;
    // Pre-fill nickname from chat store
    newEntry.value.name = chatStore.nickname || '';
    isModalOpen.value = true;
    SoundManager.playTypingSound();
};

const resetMobileScroll = () => {
    if (window.innerWidth <= 900) {
        // Wait for keyboard close animation (approx 300-350ms)
        setTimeout(() => {
            const scrollContainer = document.querySelector('.scroll-content');
            const sections = document.querySelectorAll('.page-section');
            if (scrollContainer && sections.length > 1) {
                const targetTop = sections[1].offsetTop;
                scrollContainer.scrollTo({ top: targetTop, behavior: 'smooth' });
            }
        }, 350);
    }
};

const closeModal = () => {
    isModalOpen.value = false;
    // Ensure keyboard closes if on mobile
    if (window.innerWidth <= 900) {
        keyboardStore.close();
        resetMobileScroll();
    }
};

const setRating = (val) => {
    newEntry.value.rating = val;
    SoundManager.playTypingSound();
};

const activeField = ref('message');
const nicknameInput = ref(null);

const setActive = (field) => {
    activeField.value = field;
};

const focusInput = () => {
    // Only focus if NOT mobile, otherwise we keep virtual keyboard
    if (window.innerWidth > 900) {
        nextTick(() => {
            if (activeField.value === 'message' && messageInput.value) {
                messageInput.value.focus();
            } else if (activeField.value === 'nickname' && nicknameInput.value) {
                nicknameInput.value.focus();
            }
        });
    }
};

const isVirtualMode = computed(() => keyboardStore.isVisible.value && window.innerWidth <= 900);

const openVirtualKeyboard = () => {
    // Only on mobile
    if (window.innerWidth > 900) return;
    
    keyboardStore.open((key) => {
        if (key === 'BACKSPACE') {
            if (activeField.value === 'message') {
                 newEntry.value.message = newEntry.value.message.slice(0, -1);
            } else {
                 newEntry.value.name = newEntry.value.name.slice(0, -1);
            }
        } else if (key === 'ENTER') {
            handleSubmit();
        } else {
             if (activeField.value === 'message') {
                 if (newEntry.value.message.length < 256) newEntry.value.message += key;
             } else {
                 if (newEntry.value.name.length < 30) newEntry.value.name += key;
             }
        }
    });
};

const handleInputClick = (field) => {
    setActive(field);
    if (window.innerWidth <= 900) {
        if (!keyboardStore.isVisible.value) {
           openVirtualKeyboard();
        }
    } else {
        focusInput();
    }
};

watch(isModalOpen, (newVal) => {
    if (newVal) {
        activeField.value = 'message'; // Reset to message on open
        if (window.innerWidth <= 900) {
            setTimeout(openVirtualKeyboard, 300);
        } else {
            focusInput();
        }
    }
});

const handleSubmit = async () => {
    if (isSubmitting.value || !newEntry.value.message.trim()) return;
    
    // Ensure we have a name (default to ANONYMOUS if left blank, though UI might require it)
    if (!newEntry.value.name.trim()) newEntry.value.name = 'ANONYMOUS';
    
    isSubmitting.value = true;
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(newEntry.value)
        });
        
        if (response.ok) {
            // Set cookie for 1 year
            const date = new Date();
            date.setFullYear(date.getFullYear() + 1);
            document.cookie = `guestbook_signed=true; expires=${date.toUTCString()}; path=/`;
            
            isSigned.value = true;
            showSuccess.value = true;
            await fetchEntries();
            
            setTimeout(() => {
                isModalOpen.value = false;
                showSuccess.value = false;
                newEntry.value = { name: '', message: '', rating: 5 };
                
                // Close virtual keyboard if open
                if (window.innerWidth <= 900) {
                    keyboardStore.close();
                    resetMobileScroll();
                }
            }, 2000);
        }
    } catch (err) {
        console.error('Submission failed:', err);
    } finally {
        isSubmitting.value = false;
    }
};

const formatDate = (iso) => {
    return new Date(iso).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
};

const handleKeyDown = (e) => {
    if (isModalOpen.value) {
        // If the modal is open, we consume these specific keys and prevent them from bubbling
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Escape', 'Esc', 'Enter', 'Tab'].includes(e.key)) {
            e.stopImmediatePropagation();
            e.stopPropagation();
            
            if (showSuccess.value) return; 

            if (e.key === 'Escape' || e.key === 'Esc') {
                closeModal();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeField.value = 'message';
                focusInput();
                SoundManager.playTypingSound();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeField.value = 'nickname';
                focusInput();
                SoundManager.playTypingSound();
            } else if (e.key === 'Tab') {
                e.preventDefault();
                activeField.value = (activeField.value === 'message') ? 'nickname' : 'message';
                focusInput();
                SoundManager.playTypingSound();
            } else if (e.key === 'Enter') {
                // Submit on Enter if we are in nickname field or just general submit?
                // User might want to insert newlines in message?
                // For now, Enter submits form as requested "Enter to sign"
                e.preventDefault();
                handleSubmit();
            }
        }
    } else if (e.key === 'Enter' && !isSigned.value) {
        openModal();
    } else if (e.key === ' ' && !isModalOpen.value) {
        // Spacebar scrolling for entries
        if (e.target.matches('input, textarea')) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        const grid = document.querySelector('.entries-grid');
        if (grid) {
             grid.scrollBy({ top: grid.clientHeight * 0.5, behavior: 'smooth' });
        }
    }
};

onMounted(() => {
    fetchEntries();
    checkIsSigned();
    window.addEventListener('keydown', handleKeyDown, { capture: true });
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown, { capture: true });
});
</script>

<template>
  <div class="section-content animate-in">
    <div class="header-row">
        <h3>> GUESTBOOK</h3>
        <button 
          class="sign-btn" 
          @click="openModal" 
          :disabled="isSigned"
        >
            {{ isSigned ? '[ SIGNED ]' : '' }}
        </button>
    </div>
    
    <p class="subtitle">
        <span 
          v-if="!isSigned" 
          class="enter-prompt" 
          @click="openModal"
        >Press <span class="enter-key">Enter ⏎</span> to sign the Guestbook.</span>
    </p>

    <div class="entries-grid">
      <div v-for="entry in entries" :key="entry.id" class="entry-box">
        <div class="entry-header">
          <span class="entry-name">
            {{ entry.name || 'ANONYMOUS' }}
            <span class="entry-flag" :title="entry.country_code || 'Unknown Region'">{{ getFlagEmoji(entry.country_code) }}</span>
          </span>
          <span class="entry-date">{{ formatDate(entry.timestamp) }}</span>
        </div>

        <p class="entry-message">{{ entry.message }}</p>
      </div>
    </div>

    <!-- Modal Overlay -->
    <Transition name="fade">
      <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
        <Transition name="popup-reveal" appear>
            <div class="modal-content">
              <div class="popup-header">
                <span>SIGN GUESTBOOK</span>
                <div class="esc-label" @click="closeModal">ESC</div>
              </div>
          
          <div v-if="!showSuccess" class="form-body">


            <div class="form-group">
                <label>FEEDBACK (MAX 256):</label>
                <div 
                    ref="messageWrapper"
                    class="input-wrapper focus-locked" 
                    :class="{ active: activeField === 'message' }" 
                    @click="handleInputClick('message')"
                >
                    <div v-if="isVirtualMode" class="virtual-input-display">
                        <span v-if="!newEntry.message && activeField === 'message'" class="cursor-block"> </span>
                        <span v-if="!newEntry.message" class="virtual-placeholder">type message...</span>
                        {{ newEntry.message }}<span v-if="newEntry.message && activeField === 'message'" class="cursor-block"> </span>
                    </div>
                    <input 
                      v-else
                      ref="messageInput"
                      v-model="newEntry.message" 
                      type="text" 
                      maxlength="256" 
                      placeholder="type message..."
                      :readonly="keyboardStore.isVisible.value && window.innerWidth <= 900"
                      @focus="setActive('message')"
                    />
                </div>
            </div>

            <div class="form-group">
                <label>NICKNAME:</label>
                <div 
                    ref="nicknameWrapper"
                    class="input-wrapper focus-locked" 
                    :class="{ active: activeField === 'nickname' }" 
                    @click="handleInputClick('nickname')"
                >
                    <div v-if="isVirtualMode" class="virtual-input-display">
                        <span v-if="!newEntry.name && activeField === 'nickname'" class="cursor-block"> </span>
                        <span v-if="!newEntry.name" class="virtual-placeholder">ANONYMOUS</span>
                        {{ newEntry.name }}<span v-if="newEntry.name && activeField === 'nickname'" class="cursor-block"> </span>
                    </div>
                    <input 
                      v-else
                      ref="nicknameInput"
                      v-model="newEntry.name" 
                      type="text" 
                      maxlength="30" 
                      placeholder="ANONYMOUS" 
                      :readonly="keyboardStore.isVisible.value && window.innerWidth <= 900"
                      @focus="setActive('nickname')"
                    />
                </div>
            </div>

            <div class="modal-actions">
                <div 
                  class="submit-btn-styled" 
                  :class="{ disabled: isSubmitting || !newEntry.message.trim() }"
                  @click="handleSubmit"
                >
                    <div class="submit-highlight"></div>
                    <span class="submit-text">{{ isSubmitting ? 'TRANSMITTING...' : 'TRANSMIT' }}</span>
                </div>
                <div 
                    class="enter-hint" 
                    :class="{ visible: !isSubmitting && newEntry.message.trim() }"
                > <span class="key-hint">⏎</span></div>
            </div>
          </div>

          <div v-else class="form-body">
            <div class="success-message">
                <span class="blink">></span> ENTRY RECORDED SUCCESSFULLY.
            </div>
          </div>
        </div>
        </Transition>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.section-content {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
}

.section-content h3 {
    margin: 0;
    color: var(--color-accent);
    border-bottom: 1px solid rgba(64, 224, 208, 0.3);
    display: inline-block;
    padding-bottom: 5px;
    font-size: 1.2rem;
    letter-spacing: 1px;
}

.sign-btn {
    background: transparent;
    border: none;
    color: var(--color-accent);
    font-family: 'JetBrains Mono', monospace;
    font-weight: bold;
    cursor: pointer;
    letter-spacing: 1px;
    transition: all 0.2s ease;
    padding: 5px 10px;
}

.sign-btn:hover:not(:disabled) {
    background: rgba(64, 224, 208, 0.1);
    text-shadow: 0 0 8px var(--color-accent);
}

.sign-btn:disabled {
    color: #444;
    cursor: default;
}

.subtitle {
    font-size: 1.1rem;
    color: #888;
    margin-bottom: 30px;
}

.enter-prompt {
    margin-left: 10px;
    cursor: pointer;
    transition: color 0.2s ease;
}

.enter-prompt:hover {
    color: #ccc;
}

.enter-key {
    color: var(--color-accent);
    font-weight: bold;
}

.entries-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    overflow-y: auto;
    padding-right: 10px;
    scrollbar-width: thin;
    scrollbar-color: #333 transparent;
}

.entry-box {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid #222;
    padding: 20px;
    border-radius: 10px;
    transition: border-color 0.2s ease;
}

.entry-box:hover {
    border-color: #444;
}

.entry-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 0.8rem;
}

.entry-name {
    color: var(--color-accent);
    font-weight: bold;
    letter-spacing: 1px;
}

.entry-flag {
    margin-left: 6px;
    font-size: 1rem;
    filter: grayscale(0.3);
    vertical-align: middle;
}

.entry-date {
    color: #555;
}



.entry-message {
    font-size: 1rem;
    line-height: 1.5;
    color: #ccc;
    word-break: break-word;
}

/* Modal Styling */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0.95;
}

.modal-content {
    background: #000;
    opacity: 0.95; /* Modal needs more opaque background for readability than the dial-up */
    border: 1px solid var(--color-accent);
    box-shadow: 0 0 30px rgba(64, 224, 208, 0.1);
    width: 450px;
    max-width: 90%;
    position: relative;
    overflow: hidden;
}

@media (max-width: 900px) {
    .modal-content {
        position: absolute; /* Override flex alignment */
        top: 30%; /* Move up */
        left: 50%;
        transform: translate(-50%, -30%);
    }
}

.popup-header {
    background: var(--color-accent);
    color: #000;
    padding: 4px 10px;
    font-weight: bold;
    font-size: 0.8rem;
    letter-spacing: 1px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.esc-label {
    font-size: 0.7rem;
    color: #000;
    cursor: pointer;
    font-family: 'Microgramma', monospace;
    letter-spacing: 1px;
    padding: 0 5px;
    background: rgba(0, 0, 0, 0.1);
    border: 1px solid rgba(0, 0, 0, 0.2);
    transition: all 0.2s ease;
}

.esc-label:hover {
    background: #000;
    color: var(--color-accent);
}

.form-body {
    padding: 24px 30px 9px 30px;
    position: relative;
}

/* New Fade/Blur/Zoom Highlight */
.input-wrapper::before {
    content: '';
    position: absolute;
    top: -2px; left: -2px; right: -2px; bottom: -2px;
    border: 1px solid var(--color-accent);
    background: rgba(64, 224, 208, 0.08); /* Subtle focus tint */
    box-shadow: 0 0 15px rgba(64, 224, 208, 0.1);
    z-index: 5;
    pointer-events: none;
    border-radius: 4px;
    
    /* Animation Initial State */
    opacity: 0;
    transform: scale(0.92);
    filter: blur(4px);
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.input-wrapper.active::before {
    opacity: 1;
    transform: scale(1);
    filter: blur(0);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-size: 0.75rem;
    color: #666;
    margin-bottom: 8px;
    letter-spacing: 1px;
}

.input-wrapper {
    position: relative;
    width: 100%;
}

.form-group input {
    width: 100%;
    background: #111;
    border: 1px solid #333;
    color: #fff;
    padding: 12px;
    font-family: 'Microgramma', sans-serif;
    font-size: 0.95rem;
    outline: none;
}

.star-rating-input {
    display: flex;
    gap: 8px;
    font-size: 1.5rem;
}

.interactive-star {
    cursor: pointer;
    color: #222;
    transition: transform 0.1s ease;
}

.interactive-star:hover {
    transform: scale(1.2);
}

.interactive-star.filled {
    color: #ffcc00;
}

.modal-actions {
    display: flex;
    justify-content: center; /* Keep button perfectly centered */
    align-items: center;
    position: relative;
}

.enter-hint {
    position: absolute;
    left: calc(50% + 90px); /* 180px est button width / 2 + spacing */
    top: 50%;
    transform: translateY(-50%) translateX(-10px); /* Vertical center + toggle slide */
    font-size: 0.8rem;
    color: #666;
    font-weight: bold;
    opacity: 0;
    transition: all 0.3s ease;
    pointer-events: none;
    font-family: 'Microgramma', monospace;
    letter-spacing: 1px;
    white-space: nowrap;
}

.enter-hint.visible {
    opacity: 1;
    transform: translateY(-50%) translateX(0);
}

.key-hint {
    display: inline-block;
    padding: 0 4px; /* Minimal spacing */
    color: #666;
    font-size: 1.4rem;
    vertical-align: middle;
}

.submit-btn-styled {
    position: relative;
    border-radius: 4px;
    font-family: 'Microgramma', monospace;
    font-size: 1.1rem;
    font-weight: bold;
    color: var(--color-accent);
    cursor: pointer;
    text-transform: uppercase;
    margin-top: -5px;
    margin-bottom: 5px;
    letter-spacing: 2px;
    padding: 10px 20px;
    border: 1px solid transparent; 
    transition: all 0.2s ease;
    user-select: none;
    overflow: hidden; /* For highlight containment */
    background: rgba(0,0,0,0.5); /* Slight dims */
}

.submit-highlight {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--color-accent);
    transform: translateX(-101%);
    /* "Crazy" Elastic: Big wind-up (-0.8) and big overshoot (1.9) */
    transition: transform 0.4s cubic-bezier(0.5, -0.8, 0.5, 1.9);
    z-index: 0;
    animation: glitch-jitter 4s infinite;
    transform: skewX(-20deg) translateX(-250%);
    border-radius: 3px 6px 1px 2px;
}

@keyframes glitch-jitter {
    0%, 92% { clip-path: inset(0 0 0 0); opacity: 1; }
    93% { clip-path: inset(10% 0 30% 0); opacity: 0.8; }
    95% { clip-path: inset(40% 0 10% 0); opacity: 1; }
    98% { clip-path: inset(0 0 50% 0); opacity: 0.9; }
    100% { clip-path: inset(0 0 0 0); opacity: 1; }
}

.submit-text {
    position: relative;
    z-index: 1;
    transition: color 0.3s ease;
}

/* Active State (When valid, show highlight permanently, not just hover) */
.submit-btn-styled:not(.disabled) .submit-highlight {
    transform: skewX(-10deg) translateX(0);
}

.submit-btn-styled:not(.disabled) .submit-text {
    color: #000;
}



.submit-btn-styled.disabled {
    color: #555;
    cursor: default;
    opacity: 0.5;
}

.success-message {
    text-align: center;
    color: var(--color-accent);
    font-weight: bold;
    padding: 20px 0;
}

.blink { animation: blink-fast 0.6s step-end infinite; }

.animate-in { animation: slideUp 0.3s ease-out; }
.animate-pop { animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

@keyframes slideUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes popIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes blink-fast { 50% { opacity: 0; } }

/* Transition magic */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@media (max-width: 900px) {
    .section-content {
        padding: 20px 15px 0 15px;
    }

    .entries-grid {
        grid-template-columns: 1fr;
        scrollbar-width: none;
    }
    .entries-grid::-webkit-scrollbar {
        display: none;
    }
    .section-content h3 {
        font-size: 1.1rem;
    }
    .subtitle {
        font-size: 0.9rem;
        margin-bottom: 20px;
    }
    
    /* Hide caret on mobile for "terminal" feel with virtual keyboard */
    input[readonly] {
        caret-color: transparent;
    }
    
    .virtual-input-display {
        width: 100%;
        background: #111;
        border: 1px solid #333;
        color: #fff;
        font-family: 'Microgramma', sans-serif;
        font-size: 0.95rem;
        padding: 12px;
        min-height: 43px;
        line-height: 1.5; /* Align text */
        pointer-events: none; /* Let wrapper click open keyboard */
        border-bottom: 1px solid #4e4e4e;
    }

    /* Force line height existence even when empty */
    .virtual-input-display::after {
        content: '\00a0';
        display: inline-block;
        width: 0;
        visibility: hidden;
    }
    
    .cursor-block {
        display: inline-block;
        width: 1px;
        height: 1.1em; /* Reduced from 1.4em to prevent expansion */
        background: #fff;
        margin-left: 1px;
        animation: blink 1s step-end infinite;
        vertical-align: middle;
    }
    
    .virtual-placeholder {
        color: #6f6f6f; /* Dim placeholder style */
        position: absolute;
    }
    
    @keyframes blink { 50% { opacity: 0; } }
}

/* Modal Open Animation (Copied from BootLoader) */
.popup-reveal-enter-active {
    animation: popup-open 0.5s cubic-bezier(0.19, 1, 0.22, 1);
}

.popup-reveal-leave-active {
    animation: popup-open 0.3s cubic-bezier(0.19, 1, 0.22, 1) reverse;
}

@keyframes popup-open {
    0% { opacity: 0; clip-path: inset(49% 0 49% 0); }
    30% { opacity: 1; clip-path: inset(49% 0 49% 0); }
    100% { opacity: 1; clip-path: inset(0 0 0 0); }
}

.popup-reveal-enter-active .popup-body,
.popup-reveal-enter-active .popup-header {
    animation: simple-fade 0.3s 0.3s backwards;
}

@keyframes simple-fade {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>
