<script setup>
import { computed, ref } from 'vue';
import { keyboardStore } from '../store/keyboardStore';
import SoundManager from '../sfx/SoundManager';

const isShiftActive = ref(false);

const baseRows = [
    ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
    ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P'],
    ['A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', '-', '_'],
    ['Z', 'X', 'C', 'V', 'B', 'N', 'M', ':', '(', ')']
];

const rows = computed(() => {
    return baseRows.map(row => row.map(char => {
        // Only uppercase/lowercase letters
        if (/[a-zA-Z]/.test(char)) {
            return isShiftActive.value ? char.toUpperCase() : char.toLowerCase();
        }
        return char;
    }));
});

const handleTap = (key) => {
    SoundManager.playTypingSound();
    keyboardStore.handleKey(key);
    
    // Auto-disable shift after typing a character
    if (isShiftActive.value) {
        isShiftActive.value = false;
    }
};

const toggleShift = () => {
    SoundManager.playTypingSound();
    isShiftActive.value = !isShiftActive.value;
};

const handleBackspace = () => {
    SoundManager.playTypingSound();
    keyboardStore.handleKey('BACKSPACE');
};

const handleSpace = () => {
    SoundManager.playTypingSound();
    keyboardStore.handleKey(' ');
};

const handleEnter = () => {
    SoundManager.playTypingSound();
    keyboardStore.handleKey('ENTER');
    // keyboardStore.close(); // Removed to keep keyboard open
};

const handleClose = () => {
    keyboardStore.close();
};

</script>

<template>
    <transition name="slide-up">
        <div v-if="keyboardStore.isVisible.value" class="virtual-keyboard-overlay">
            <div class="keyboard-body">
                <!-- Render standard rows first -->
                <div v-for="(row, rIndex) in rows" :key="rIndex" class="kb-row">
                    <!-- Prepend Shift Key to Row 3 (Index 3, which is the 4th row) -->
                    <button 
                         v-if="rIndex === 3"
                         class="kb-key action-key shift-key"
                         :class="{ 'active': isShiftActive }"
                         @click.stop="toggleShift"
                    >
                        ⬆
                    </button>

                    <button 
                        v-for="char in row" 
                        :key="char" 
                        class="kb-key"
                        @click.stop="handleTap(char)"
                    >
                        {{ char }}
                    </button>
                    
                    <!-- Append Backspace (DEL) to Row 3 if we move it from bottom bar? No, keep DEL at bottom. -->
                </div>
                
                <!-- Bottom Action Row -->
                <div class="kb-row actions">
                   <button class="kb-key action-key wide" @click.stop="handleSpace">SPACE</button>
                   <button class="kb-key action-key" @click.stop="handleBackspace">DEL</button>
                   <button class="kb-key action-key enter" @click.stop="handleEnter">ENTER ⏎</button>
                </div>
            </div>
        </div>
    </transition>
</template>

<style scoped>
.virtual-keyboard-overlay {
    position: absolute; /* Changed from fixed to absolute to fit in container */
    bottom: 0;
    left: 0;
    width: 100%;
    background: rgba(0, 0, 0, 0.85); /* Slightly transparent */
    border-top: none; /* Removed border */
    /* box-shadow: 0 -5px 20px rgba(0,0,0,0.8); Remove shadow or keep? */
    z-index: 30; /* Below scanlines (50) but above content (20) */
    display: flex;
    flex-direction: column;
    padding-bottom: 20px; /* Safe area */
    font-family: 'Microgramma', monospace;
    user-select: none;
    backdrop-filter: blur(5px);
}

.keyboard-body {
    padding: 10px 5px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.kb-row {
    display: flex;
    justify-content: center;
    gap: 4px;
}

.kb-key {
    background: #1a1a1a;
    color: #fff;
    border: 1px solid #333;
    border-radius: 4px;
    font-family: inherit;
    font-size: 1.1rem;
    height: 44px;
    min-width: 24px;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 0 #000;
    transform: translateY(0);
    transition: all 0.05s;
    max-width: 45px;
}

.kb-key:active {
    transform: translateY(2px);
    box-shadow: 0 1px 0 #000;
    background: var(--color-accent);
    color: #000;
}

.actions .kb-key {
    font-size: 0.8rem;
    font-weight: bold;
    max-width: none; /* Let them grow */
}

.action-key {
    background: #222;
    border-color: #444;
}

.action-key.wide {
    flex: 3;
}

.action-key.enter {
    background: rgba(64, 224, 208, 0.1);
    color: var(--color-accent);
    border-color: var(--color-accent);
    flex: 1.5;
}

.slide-up-enter-active,
.slide-up-leave-active {
    transition: transform 0.3s cubic-bezier(0.2, 0.8, 0.2, 1);
}

.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
}

.shift-key {
    background: #222;
    border-color: #444;
    color: #888;
    max-width: 45px; /* Same max as keys */
}

.shift-key.active {
    background: rgba(64, 224, 208, 0.2);
    border-color: var(--color-accent);
    color: var(--color-accent);
    box-shadow: 0 0 10px rgba(64, 224, 208, 0.2);
}
</style>
