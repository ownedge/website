<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const companiesOriginal = [
  "SYNDISCAPE.COM",
  "FLOWSTASH.IO",
  "ZIZARA.PT",
];

const portfolioTitleFull = "PORTFOLIO";
const portfolioText = ref("");
const typedCompanies = ref(companiesOriginal.map(() => ""));
const activeIndex = ref(-1); // -1 = Title, 0-3 = Companies
const showCursor = ref(false); // To blink only when active
const isVisible = ref(false);

const sectionRef = ref(null);
let typingTimeout = null;

const typeText = (targetRef, fullText, callback) => {
  let charIndex = 0;
  showCursor.value = true;
  
  const typeChar = () => {
    if (charIndex <= fullText.length) {
      targetRef.value = fullText.substring(0, charIndex);
      charIndex++;
      typingTimeout = setTimeout(typeChar, 100); // Speed
    } else {
      showCursor.value = false;
      if (callback) callback();
    }
  };
  
  typeChar();
};

const startTypingSequence = () => {
  if (isVisible.value) return; // Already started
  isVisible.value = true;

  // 1. Type Title
  activeIndex.value = -1; // Specific ID for title
  typeText(portfolioText, portfolioTitleFull, () => {
    // 2. Start Companies Sequence
    typeNextCompany(0);
  });
};

const typeNextCompany = (index) => {
  if (index < companiesOriginal.length) {
    activeIndex.value = index;
    // We need a way to update the specific item in the array
    // Since primitives in array ref aren't directly writable via a single ref like 'targetRef', we use a wrapper or direct assignment.
    
    let charIndex = 0;
    const fullText = companiesOriginal[index];
    showCursor.value = true;

    const typeChar = () => {
      if (charIndex <= fullText.length) {
        typedCompanies.value[index] = fullText.substring(0, charIndex);
        charIndex++;
        typingTimeout = setTimeout(typeChar, 80);
      } else {
        showCursor.value = false;
        setTimeout(() => typeNextCompany(index + 1), 300); // Pause between items
      }
    };
    
    typeChar();
  } else {
    activeIndex.value = -2; // Done
  }
};

onMounted(() => {
  const observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting) {
      startTypingSequence();
      observer.disconnect(); // Run once
    }
  }, { threshold: 0.3 }); // Trigger when 30% visible

  if (sectionRef.value) {
    observer.observe(sectionRef.value);
  }
});

onUnmounted(() => {
  clearTimeout(typingTimeout);
});
</script>

<template>
  <div class="children-companies" ref="sectionRef">
    <div class="content">
      <h2 class="section-title">
        <span class="typing-wrapper">
          {{ portfolioText }}<span v-if="activeIndex === -1" class="cursor">█</span>
        </span>
      </h2>
      <ul class="company-list">
        <li v-for="(company, index) in typedCompanies" :key="index" class="company-item">
          <span class="typing-wrapper">
            {{ company }}<span v-if="activeIndex === index" class="cursor">█</span>
          </span>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.children-companies {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100vh;
  width: 100%;
  position: relative;
  scroll-snap-align: start; /* Snap to start of this section */
}

.content {
  text-align: center;
}

.section-title {
  font-size: 2rem;
  letter-spacing: 0.5em;
  color: rgba(255, 255, 255, 0.5);
  margin-bottom: 60px;
  text-transform: uppercase;
  min-height: 1.2em; /* Reserve space */
}

.company-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 40px;
}

.company-item {
  font-size: 4vw;
  font-weight: bold;
  letter-spacing: 0.2em;
  color: #fff;
  text-transform: uppercase;
  /* Initial state */
  text-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
  transition: all 0.3s ease;
  cursor: pointer;
  pointer-events: auto; /* Enable hover interaction */
  min-height: 1.2em; /* Reserve space */
}

.company-item:hover {
  color: var(--color-accent);
  text-shadow: 
    0 0 10px var(--color-accent),
    0 0 20px var(--color-accent),
    0 0 40px var(--color-accent);
  transform: scale(1.05);
}

.typing-wrapper {
  display: inline-block;
  position: relative;
}

.cursor {
  display: inline-block;
  position: absolute;
  left: 100%;
  bottom: 0;
  animation: blink 0.8s step-end infinite;
  color: var(--color-accent);
  text-shadow: 0 0 10px var(--color-accent);
  margin-left: 5px;
  vertical-align: baseline;
  width: 1ch;
}

/* Specific cursor color for title if desired, or keep accent */
.section-title .cursor {
    color: rgba(255, 255, 255, 0.5);
    text-shadow: none;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0; }
}

@media (max-width: 768px) {
  .company-item {
    font-size: 8vw;
    gap: 30px;
  }
}
</style>


