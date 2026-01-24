import { ref, computed } from 'vue';

const isVisible = ref(false);
// Use plain variables for callbacks to avoid Vue reactivity proxying issues with functions
let inputCallback = null;
let closeCallback = null;

export const keyboardStore = {
    isVisible,
    
    open(onInput, onClose) {
        // if (!isMobile.value) return; // Removed restriction, let caller decide
        inputCallback = onInput;
        closeCallback = onClose;
        isVisible.value = true;
    },
    
    close() {
        isVisible.value = false;
        if (closeCallback) {
            closeCallback();
        }
        inputCallback = null;
        closeCallback = null;
    },
    
    handleKey(key) {
        // console.log('KeyboardStore handleKey:', key);
        if (inputCallback) {
            inputCallback(key);
        } else {
            console.warn('KeyboardStore: No input callback registered!');
        }
    }
};
