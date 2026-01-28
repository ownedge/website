<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import SoundManager from '../../sfx/SoundManager';
import { chatStore } from '../../store/chatStore';

const isModalOpen = ref(false);
const isSending = ref(false);
const showSuccess = ref(false);
const emailForm = ref({
    name: '',
    subject: 'hello from the website',
    message: ''
});

const activeField = ref('name');
const nameInput = ref(null);
const subjectInput = ref(null);
const messageInput = ref(null);

const setActive = (field) => {
    activeField.value = field;
};

const focusInput = () => {
    nextTick(() => {
        if (activeField.value === 'name' && nameInput.value) nameInput.value.focus();
        else if (activeField.value === 'subject' && subjectInput.value) subjectInput.value.focus();
        else if (activeField.value === 'message' && messageInput.value) messageInput.value.focus();
    });
};

const openModal = () => {
    emailForm.value.name = chatStore.nickname || '';
    emailForm.value.subject = 'hello from the website';
    
    isModalOpen.value = true;
    activeField.value = 'message'; // Reset to message
    focusInput();
    SoundManager.playTypingSound();
};

const API_URL = import.meta.env.PROD 
  ? '/api/send_report.php' 
  : '/api/send_report.php';

const closeModal = () => {
    if (isSending.value) return;
    isModalOpen.value = false;
    showSuccess.value = false;
};

const sendEmail = async () => {
    if (!emailForm.value.message.trim() || isSending.value) return;
    
    isSending.value = true;
    SoundManager.playTypingSound();

    // Simulate Network Delay for Effect (min 1000ms)
    const delayPromise = new Promise(resolve => setTimeout(resolve, 1000));
    
    try {
        const payload = {
            name: emailForm.value.name,
            subject: emailForm.value.subject,
            message: emailForm.value.message
        };

        const fetchPromise = fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const [response] = await Promise.all([fetchPromise, delayPromise]);
        
        if (response.ok) {
            showSuccess.value = true;
            
            setTimeout(() => {
                closeModal();
                // Reset form
                emailForm.value.message = '';
                showSuccess.value = false;
            }, 1500);
        } else {
             console.error('Transmission failed', await response.text());
        }
    } catch (e) {
        console.error('Transmission error', e);
    } finally {
        isSending.value = false;
    }
};

const handleFKey = (key) => {
    if (key === 'F3') {
        window.open('https://www.linkedin.com/in/pedrocatalao', '_blank');
        SoundManager.playTypingSound();
    } else if (key === 'F4') {
        openModal();
    }
};

const handleKeydown = (e) => {
    if (isModalOpen.value) {
        // Prevent bubbling for key nav
        if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'Escape', 'Esc', 'Enter'].includes(e.key)) {
             e.stopPropagation();
        }

        if (e.key === 'Escape' || e.key === 'Esc') {
            closeModal();
        } else if (e.key === 'Enter') {
            // Prevent default newlines if submitting, unless Shift+Enter?
            if (!e.shiftKey) { 
                e.preventDefault();
                sendEmail();
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (activeField.value === 'message') activeField.value = 'subject';
            else if (activeField.value === 'subject') activeField.value = 'name';
            focusInput();
            SoundManager.playTypingSound();
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (activeField.value === 'name') activeField.value = 'subject';
            else if (activeField.value === 'subject') activeField.value = 'message';
            focusInput();
            SoundManager.playTypingSound();
        }
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});

defineExpose({
    handleFKey
});
</script>

<template>
  <div class="section-content animate-in">
    <h3>> WHY</h3>
    
    <div class="manifesto-wrapper">
      <div class="manifesto-content">
        <p class="highlight">Ownedge was born from a simple idea: that the most meaningful work happens when you care deeply about what you build — and when you choose to stand behind it.</p>
        
        <p>We live in a world full of fast launches, disposable products, and short-term thinking. Ownedge exists as a quiet counterweight to that. It is a place to build deliberately and with craftsmanship, whether that means software, infrastructure, products, or real-world assets.</p>
        
        <p>At its core, Ownedge is about ownership in the deepest sense: not just legal ownership, but intellectual ownership, responsibility, and pride in what exists because you made it.</p>
        
        <p>We design systems the way artisans shape objects: with attention to detail, structure, balance, and longevity. </p>

        <p>Our work is not about chasing trends — <span>We set the trends.</span></p>
        <div class="meta">- P.</div>
      </div>

      <div class="ceo-profile">
        <div class="photo-frame">
          <img src="../../assets/juca.png" alt="CEO" class="ceo-photo" />
          <div class="frame-label">MY.IMG</div>
        </div>
        <div class="signature">
          <div class="signature-line">
            <a href="https://www.linkedin.com/in/pedrocatalao" target="_blank" class="linkedin-icon-link" title="">
              <svg viewBox="0 0 24 24" class="linkedin-svg">
                <path d="M24 24H0V0h24v24zM8.1 19V9H5.4v10h2.7zM6.8 7.8c0.9 0 1.6-0.7 1.6-1.6c0-0.9-0.7-1.6-1.6-1.6c-0.9 0-1.6 0.7-1.6 1.6C5.1 7.1 5.8 7.8 6.8 7.8zM19 19v-5.6c0-2.7-0.6-4.8-3.7-4.8c-1.5 0-2.5 0.8-2.9 1.6H12.3V9h-2.6v10h2.7v-4.3c0-1.1 0.2-2.2 1.6-2.2c1.3 0 1.4 1.3 1.4 2.3V19H19z"/>
              </svg>
            </a>PEDRO C.
          </div>
          <div class="signature-title">FOUNDER</div>
        </div>
      </div>
    </div>

    <!-- Email Modal -->
    <Transition name="fade">
      <div v-if="isModalOpen" class="modal-overlay" @click.self="closeModal">
        <Transition name="popup-reveal" appear>
            <div class="modal-content">
              <div class="popup-header">
                <span>COMMS LINK</span>
                <div class="esc-label" @click="closeModal">ESC</div>
              </div>
          
          <div v-if="!showSuccess" class="form-body">
             <div class="form-group">
                <label>NICKNAME:</label>
                <div 
                    class="input-wrapper focus-locked" 
                    :class="{ active: activeField === 'name' }"
                    @click="setActive('name')"
                >
                    <input 
                        ref="nameInput"
                        type="text" 
                        v-model="emailForm.name" 
                        placeholder="YOUR NAME" 
                        @focus="setActive('name')"
                    />
                </div>
             </div>

             <div class="form-group">
                <label>SUBJECT:</label>
                <div 
                    class="input-wrapper focus-locked" 
                    :class="{ active: activeField === 'subject' }"
                    @click="setActive('subject')"
                >
                    <input 
                        ref="subjectInput"
                        type="text" 
                        v-model="emailForm.subject" 
                        placeholder="SUBJECT" 
                        @focus="setActive('subject')"
                    />
                </div>
             </div>
             
             <div class="form-group">
                <label>MESSAGE:</label>
                <div 
                    class="input-wrapper focus-locked" 
                    :class="{ active: activeField === 'message' }"
                    @click="setActive('message')"
                >
                    <textarea 
                      ref="messageInput"
                      v-model="emailForm.message" 
                      placeholder="ENTER MESSAGE..." 
                      class="custom-textarea"
                      @focus="setActive('message')"
                    ></textarea>
                </div>
             </div>

            <div class="modal-actions">
                <div 
                  class="submit-btn-styled" 
                  :class="{ disabled: isSending || !emailForm.message.trim() }"
                  @click="sendEmail"
                >
                    <div class="submit-highlight"></div>
                    <span class="submit-text">{{ isSending ? 'TRANSMITTING...' : 'TRANSMIT' }}</span>
                </div>
                <div 
                    class="enter-hint" 
                    :class="{ visible: !isSending && emailForm.message.trim() }"
                > <span class="key-hint">⏎</span></div>
            </div>
          </div>

          <div v-else class="form-body">
            <div class="success-message">
                <span class="blink">></span> TRANSMISSION INITIATED.
            </div>
          </div>
        </div>
        </Transition>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
:host, .section-content {
    --linkedin-icon-size: 16px;
}

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

.manifesto-wrapper {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 40px;
    align-items: start;
}

.manifesto-content {
    max-width: 900px;
}

.manifesto-content p {
    line-height: 1.5;
    color: rgba(255, 255, 255, 0.85);
    font-size: 1.1rem;
    margin-bottom: 20px;
}

.manifesto-content p.highlight {
    color: #fff;
    font-size: 1.35rem;
    font-weight: 500;
    line-height: 1.5;
    border-left: 3px solid var(--color-accent);
    padding-left: 20px;
    margin-bottom: 30px;
}

.meta {
    margin-top: 30px;
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 20px;
    font-family: 'Microgramma', 'Courier New', monospace;
}

.trend-statement {
    margin-top: 40px;
    padding: 25px;
    background: rgba(64, 224, 208, 0.05);
    border-left: 4px solid var(--color-accent);
    font-size: 1.3rem;
    color: #fff;
    letter-spacing: 1px;
    max-width: fit-content;
}

.trend-statement span {
    color: var(--color-accent);
    font-weight: bold;
    text-transform: uppercase;
}

/* CEO Profile */
.ceo-profile {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
    padding-top: 20px;
}

.photo-frame {
    padding: 10px;
    background: #111;
    border: 1px solid #444;
    position: relative;
    box-shadow: 10px 10px 0px rgba(0,0,0,0.3);
}

.photo-frame::before {
    content: '';
    position: absolute;
    top: -1px;
    left: -1px;
    width: 10px;
    height: 10px;
    border-top: 2px solid var(--color-accent);
    border-left: 2px solid var(--color-accent);
}

.ceo-photo {
    width: 220px;
    height: auto;
    filter: grayscale(1) contrast(1.8) brightness(0.8);
    display: block;
    mix-blend-mode: screen;
    opacity: 0.79;
}

.frame-label {
    position: absolute;
    bottom: -8px;
    right: 10px;
    background: #000;
    padding: 0 5px;
    font-size: 0.7rem;
    color: #666;
    letter-spacing: 1px;
}

.signature {
    text-align: right;
    width: 100%;
    padding-right: 20px;
}

.signature-line {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.4rem;
    color: #fff;
    font-style: italic;
    letter-spacing: -1px;
    margin-bottom: 5px;
}

.signature-title {
    font-size: 0.7rem;
    color: var(--color-accent);
    letter-spacing: 2px;
    margin-bottom: 10px;
}

.linkedin-icon-link {
    display: inline-flex;
    align-items: center;
    transition: all 0.3s ease;
    opacity: 0.5;
    margin-right: 8px;
    vertical-align: middle;
}

.linkedin-svg {
    width: var(--linkedin-icon-size);
    height: var(--linkedin-icon-size);
    fill: #fff;
    filter: drop-shadow(0 0 0px var(--color-accent));
}

.linkedin-icon-link:hover {
    opacity: 1;
    transform: translateY(-2px);
}

.linkedin-icon-link:hover .linkedin-svg {
    fill: var(--color-accent);
    filter: drop-shadow(0 0 8px var(--color-accent));
}

.animate-in {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 900px) {
    .manifesto-wrapper {
        grid-template-columns: 1fr;
        gap: 25px;
    }

    .section-content h3 {
        font-size: 1.1rem;
        margin-bottom: 15px;
    }
    
    .ceo-profile {
        align-items: center;
        text-align: center;
        padding-top: 20px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    
    .signature {
        text-align: center;
        margin-top: 10px;
        padding-right: 0;
    }

    .manifesto-content p {
        font-size: 0.95rem;
        margin-bottom: 15px;
    }

    .manifesto-content p.highlight {
        font-size: 1.1rem;
        padding-left: 15px;
        margin-bottom: 20px;
    }

    .ceo-photo {
        width: 150px;
    }

    .signature-line {
        font-size: 1.1rem;
    }
}

/* Modal Styling */
.modal-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0.95;
}

.modal-content {
    background: #000;
    opacity: 0.95; 
    border: 1px solid var(--color-accent);
    box-shadow: 0 0 30px rgba(64, 224, 208, 0.1);
    width: 450px;
    max-width: 90%;
    position: relative;
    overflow: hidden;
}

@media (max-width: 900px) {
    .modal-content {
        position: absolute; 
        top: 30%; 
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
    display: flex;
    flex-direction: column;
}

/* Focus/Blur Highlight */
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

.input-wrapper.active::before,
.input-wrapper:focus-within::before {
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

.form-group input, .custom-textarea {
    width: 100%;
    background: #111;
    border: 1px solid #333;
    color: #fff;
    padding: 12px;
    font-family: 'Microgramma', sans-serif;
    font-size: 0.95rem;
    outline: none;
}

.custom-textarea {
    height: 100px;
    resize: none;
    display: block;
}

/* Submit Button */
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
    overflow: hidden; 
    background: rgba(0,0,0,0.5); 
    display: inline-block;
}

.submit-highlight {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: var(--color-accent);
    transform: translateX(-101%);
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

/* Active State */
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

.modal-actions {
    display: flex;
    justify-content: center;
    position: relative;
    align-items: center;
}

.enter-hint {
    position: absolute;
    left: calc(50% + 90px);
    top: 50%;
    transform: translateY(-50%) translateX(-10px);
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
    padding: 0 4px;
    color: #666;
    font-size: 1.4rem;
    vertical-align: middle;
}

.success-message {
    text-align: center;
    color: var(--color-accent);
    font-weight: bold;
    padding: 20px 0;
}

.blink { animation: blink-fast 0.6s step-end infinite; }
@keyframes blink-fast { 50% { opacity: 0; } }


/* Transitions */
@keyframes popIn {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}

.popup-reveal-enter-active {
  animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.popup-reveal-leave-active {
  transition: opacity 0.2s ease;
}
.popup-reveal-leave-to {
  opacity: 0;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.4s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>
