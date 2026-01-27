<script setup>
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue';
import SoundManager from '../../sfx/SoundManager';

const posts = ref([]);
const activePostId = ref(null);
const postContent = ref('');
const isLoading = ref(false);
const error = ref(null);

import { inject } from 'vue';

const activePost = computed(() => posts.value.find(p => p.id === activePostId.value));
const isTitleOverflowing = ref(false);

const checkTitleOverflow = async () => {
    isTitleOverflowing.value = false;
    await nextTick();
    // Select the active title directly from DOM to avoid complex ref management in loop
    const activeEl = document.querySelector('.menu-item.active .post-title');
    if (activeEl) {
        // Check if content width > container width (approx 110-120px)
        // We compare scrollWidth (content) vs a fixed logical width for the mobile card
        isTitleOverflowing.value = activeEl.scrollWidth > 110;
    }
};

watch(activePostId, () => {
   checkTitleOverflow(); 
});

// Injected from App.vue (if routing via /blog/:id)
const injectedPostId = inject('initialBlogPostId', ref(null));
const requestedPostId = ref(null);

const fetchIndex = async () => {
    try {
        // Cache bust the list to ensure we see new posts immediately
        const res = await fetch(`/api/blog.php?action=list&t=${Date.now()}`);
        if (res.ok) {
            posts.value = await res.json();
            if (posts.value.length > 0) {
                // Priority: Injected ID (Path) > Query Param > Session Storage > First Post
                let targetId = posts.value[0].id;
                
                const pathId = injectedPostId.value;
                const queryId = requestedPostId.value;

                if (pathId && posts.value.find(p => p.id === pathId)) {
                    targetId = pathId;
                } else if (queryId) {
                    if (posts.value.find(p => p.id === queryId)) {
                        targetId = queryId;
                    } else {
                        console.warn(`Deep link post "${queryId}" not found in index.`);
                    }
                } else {
                    const savedId = sessionStorage.getItem('last_blog_post_id');
                    if (savedId && posts.value.find(p => p.id === savedId)) {
                        targetId = savedId;
                    }
                }
                
                // Don't trigger URL update for initial selection to avoid replacing history state excessively
                selectPost(targetId, true);
            }
        } else {
            error.value = 'Failed to load blog index';
        }
    } catch (e) {
        error.value = 'Network error loading blog';
    }
};

const postStats = ref({ views: 0, kudos: 0 });
const showCopyHint = ref(false);

const selectPost = async (id, isInitial = false) => {
    if (activePostId.value === id && postContent.value) return;
    
    activePostId.value = id;
    sessionStorage.setItem('last_blog_post_id', id);
    if (!isInitial) SoundManager.playTypingSound();

    // Check Like Status
    const likedPosts = JSON.parse(localStorage.getItem('liked_posts') || '[]');
    hasLiked.value = likedPosts.includes(id);

    // Update Browser URL (Clean Routing)
    // Only if we aren't already on this path
    const currentPath = window.location.pathname;
    const targetPath = `/blog/${id}`;
    if (!isInitial && currentPath !== targetPath) {
        history.pushState({ index: 3 }, '', targetPath);
    }
    
    const post = posts.value.find(p => p.id === id);
    if (!post) return;
    
    isLoading.value = true;
    error.value = null;
    postContent.value = ''; // Clear prev content
    
    // Fetch content and register view in parallel
    try {
        const [contentRes, statsRes] = await Promise.all([
            fetch(`/blog/${post.file}?t=${Date.now()}`), // Cache bust content too
            fetch(`/api/blog.php?action=view&id=${id}&t=${Date.now()}`) 
        ]);

        if (contentRes.ok) {
            postContent.value = await contentRes.text();
        } else {
            postContent.value = '<p>Error loading post content.</p>';
        }

        if (statsRes.ok) {
            postStats.value = await statsRes.json();
        }

    } catch (e) {
        postContent.value = '<p>Network error.</p>';
        console.error(e);
    } finally {
        isLoading.value = false;
    }
};

const hasLiked = ref(false);

const giveKudo = async () => {
    if (!activePostId.value) return;
    
    // Check Local Storage
    const likedPosts = JSON.parse(localStorage.getItem('liked_posts') || '[]');
    if (likedPosts.includes(activePostId.value)) {
        return; // Already liked
    }
    
    SoundManager.playTypingSound();
    try {
        const res = await fetch(`/api/blog.php?action=kudo&id=${activePostId.value}`);
        if (res.ok) {
            postStats.value = await res.json();
            
            // Save to Local Storage
            likedPosts.push(activePostId.value);
            localStorage.setItem('liked_posts', JSON.stringify(likedPosts));
            hasLiked.value = true;
        }
    } catch (e) {
        console.error('Failed to give kudo', e);
    }
};

const copyLink = async () => {
    if (!activePostId.value) return;
    SoundManager.playTypingSound();
    
    // Generate Pretty URL
    const url = `${window.location.origin}/blog/${activePostId.value}`;
    
    try {
        await navigator.clipboard.writeText(url);
        // Visual feedback
        showCopyHint.value = true;
        setTimeout(() => {
            showCopyHint.value = false;
        }, 2000);

        // Report Share
        fetch(`/api/blog.php?action=share&id=${activePostId.value}`);
    } catch (err) {
        console.error('Failed to copy', err);
    }
};

const handleKeydown = (e) => {
  if (posts.value.length === 0) return;
  const currentIndex = posts.value.findIndex(p => p.id === activePostId.value);
  
  if (e.key === 'ArrowDown') {
      e.preventDefault();
      e.stopImmediatePropagation();
      const nextIndex = (currentIndex + 1) % posts.value.length;
      selectPost(posts.value[nextIndex].id);
  } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      e.stopImmediatePropagation();
      const prevIndex = (currentIndex - 1 + posts.value.length) % posts.value.length;
      selectPost(posts.value[prevIndex].id);
  } else if (e.key === ' ') {
      // Spacebar Scroll
      e.preventDefault();
      e.stopImmediatePropagation();
      const contentCol = document.querySelector('.post-content-column');
      if (contentCol) {
          contentCol.scrollBy({ top: contentCol.clientHeight * 0.5, behavior: 'smooth' });
      }
  }
};

onMounted(() => {
    // Capture param synchronously on mount to avoid race conditions with router
    const params = new URLSearchParams(window.location.search);
    requestedPostId.value = params.get('post');
    
    fetchIndex();
    window.addEventListener('keydown', handleKeydown, { capture: true });
    // Check overflow after initial index load and render
    setTimeout(checkTitleOverflow, 500); 
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown, { capture: true });
});
</script>

<template>
  <div class="section-content animate-in">
    <h3>> LOGS</h3>
    
    <div class="blog-layout">
      <!-- Left Menu: Post List -->
      <div class="blog-menu">
        <div v-if="error" class="error-msg">{{ error }}</div>
        <div v-else-if="posts.length === 0 && !isLoading" class="empty-list">NO ARCHIVES</div>
        
        <div 
          v-for="post in posts" 
          :key="post.id"
          class="menu-item"
          :class="{ active: activePostId === post.id }"
          @click="selectPost(post.id)"
        >
          <span class="indicator">></span>
          <div class="post-info">
            <span class="post-title" :class="{ marquee: activePostId === post.id && isTitleOverflowing }">{{ post.title }}</span>
            <span class="post-date">{{ post.date }}</span>
          </div>
        </div>
      </div>

      <!-- Right Content: Post Display -->
      <div class="blog-viewport">
        <Transition name="fade" mode="out-in">
            <div v-if="isLoading" key="loading" class="loading-state">
                LOADING DATA...
            </div>
            <div v-else-if="postContent" key="content" class="post-layout">
                <!-- Content Column -->
                <div class="post-content-column custom-scroll">
                    <div class="post-header-meta" v-if="activePost">
                         <h1>{{ activePost.displayTitle || activePost.title }}</h1>
                    </div>
                    <!-- V-HTML Secure Content -->
                    <div class="blog-body" v-html="postContent"></div>
                </div>

                <!-- Stats Column -->
                <div class="post-stats-column">
                    <div class="stat-item">
                        <span class="stat-label">VIEWS</span>
                        <span class="stat-value">{{ postStats.views }}</span>
                    </div>
                    <div class="stat-item interactive" @click="giveKudo" :class="{ disabled: hasLiked }">
                        <span class="stat-label">KUDOS</span>
                        <div class="kudos-wrapper">
                            <span class="kudos-icon" :class="{ liked: hasLiked }">♥</span>
                            <span class="stat-value">{{ postStats.kudos }}</span>
                        </div>
                    </div>
                    <div class="stat-item interactive" @click="copyLink" style="position: relative;">
                         <span class="stat-label">SHARE</span>
                         <span class="stat-icon" style="font-size: 1.4rem; line-height: 1;">☍</span>
                         <Transition name="fade">
                            <span v-if="showCopyHint" class="copy-hint">COPIED</span>
                         </Transition>
                    </div>
                </div>
            </div>
            <div v-else key="empty" class="empty-state">
                SELECT A LOG
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
    align-self: flex-start;
}

.section-content {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.blog-layout {
    display: grid;
    grid-template-columns: 250px 1fr;
    gap: 40px;
    height: 100%;
    min-height: 0;
    width: 100%;
}

.error-msg {
    color: #ff4444;
    font-size: 0.8rem;
    padding: 10px;
}

.empty-list {
    color: #666;
    padding: 10px;
    font-size: 0.8rem;
}

/* Left Menu */
.blog-menu {
    display: flex;
    flex-direction: column;
    gap: 15px;
    border-right: 1px solid rgba(255,255,255,0.1);
    padding-right: 20px;
    overflow-y: auto; /* Allow menu scrolling if needed */
    scrollbar-width: thin;
    scrollbar-color: #333 transparent;
}

.menu-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
    color: #666;
    transition: all 0.2s ease;
    padding: 5px 0;
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
    margin-top: 2px;
}

.menu-item.active .indicator {
    opacity: 1;
}

.post-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.post-title {
    font-weight: bold;
    font-size: 0.95rem;
    text-transform: uppercase;
}

.post-date {
    font-size: 0.75rem;
    opacity: 0.6;
    font-family: 'Microgramma', 'Courier New', monospace;
}

/* Right Viewport */
.blog-viewport {
    flex: 1;
    position: relative;
    overflow: hidden; 
    display: flex;
    flex-direction: column;
    min-height: 0; /* Critical for nested flex scrolling */
}

.post-layout {
    display: flex;
    flex: 1;
    height: 100%;
    gap: 30px;
    animation: fadeIn 0.3s ease;
    overflow: hidden; /* Ensure split pane contains scroll */
}

.post-content-column {
    flex: 2;
    overflow-y: auto;
    padding-right: 15px;
    scrollbar-width: thin;
    scrollbar-color: rgba(62, 229, 212, 0.812) transparent;
    height: 100%; /* Ensure it fills parent */
}

/* Custom Scroll for inner content */
.post-content-column::-webkit-scrollbar { width: 6px; }
.post-content-column::-webkit-scrollbar-thumb { background: rgba(64, 224, 208, 0.4); }
.post-content-column::-webkit-scrollbar-track { background: #111; }

.post-stats-column {
    width: 60px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding-top: 10px;
    align-items: center;
    border-left: 1px dashed rgba(255,255,255,0.1);
    padding-left: 10px;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}

.stat-label {
    font-size: 0.6rem;
    color: #666;
    letter-spacing: 1px;
}

.stat-value {
    font-size: 0.9rem;
    font-weight: bold;
    color: var(--color-accent);
}

.stat-item.interactive {
    cursor: pointer;
    transition: transform 0.1s;
}

.stat-item.interactive:active {
    transform: scale(0.95);
}

.stat-item.interactive.disabled {
    cursor: default;
    opacity: 0.8;
}

.stat-item.interactive.disabled:active {
    transform: none;
}

.kudos-wrapper {
    display: flex;
    flex-direction: column; 
    align-items: center;
    gap: 2px;
}

.kudos-icon {
    font-size: 1.2rem;
    color: #666; /* Default Grey */
    transition: color 0.3s ease;
}

.kudos-icon.liked {
    color: #ff4444; /* Red when liked */
}

.stat-icon {
    font-size: 1.2rem;
    color: var(--color-accent);
}

.copy-hint {
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: var(--color-accent);
    color: #000;
    font-size: 0.6rem;
    font-weight: bold;
    padding: 2px 4px;
    border-radius: 2px;
    margin-top: 5px;
    letter-spacing: 1px;
    pointer-events: none;
    white-space: nowrap;
}

.post-content-wrapper {
    animation: fadeIn 0.3s ease;
}

.post-header-meta h1 {
    margin-top: 0;
    font-size: 1.6rem;
    color: #fff;
    margin-bottom: 30px;
    letter-spacing: 1px;
    border-bottom: 1px dashed rgba(255,255,255,0.1);
    padding-bottom: 15px;
}

/* Post Body Styling (Deep selector not strictly needed with scoped v-html but good practice) */
:deep(.blog-body) {
    font-size: 1.05rem;
    line-height: 1.7;
    color: rgba(255,255,255,0.85);
}

:deep(.blog-body h2) {
    color: var(--color-accent);
    font-size: 1.3rem;
    margin-top: 30px;
    margin-bottom: 15px;
}

:deep(.blog-body h3) {
    color: #fff;
    font-size: 1.1rem;
    margin-top: 25px;
    margin-bottom: 10px;
}

:deep(.blog-body p) {
    margin-bottom: 20px;
}

:deep(.blog-body ul) {
    margin-bottom: 20px;
    padding-left: 20px;
}

:deep(.blog-body li) {
    margin-bottom: 8px;
}

:deep(.blog-body blockquote) {
    border-left: 3px solid var(--color-accent);
    margin: 20px 0;
    padding-left: 20px;
    font-style: italic;
    color: #aaa;
}

:deep(.blog-body .meta) {
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 20px;
    font-family: 'Microgramma', 'Courier New', monospace;
}

.loading-state, .empty-state {
    color: #666;
    font-family: 'Microgramma', 'Courier New', monospace;
    margin-top: 50px;
    display: block;
}

.animate-in {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}

@media (max-width: 900px) {
    .blog-layout {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    .blog-menu {
        flex-direction: row;
        border-right: none;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding-right: 0;
        padding-bottom: 15px;
        overflow-x: auto;
        gap: 10px; /* Gap between items */
        scrollbar-width: none;
    }
    
    .menu-item {
        flex-direction: column;
        width: 120px; /* Smaller reserved space */
        flex-shrink: 0;
        padding: 8px;
        background: rgba(255, 255, 255, 0.05); /* Subtle background for separation */
        border: 1px solid transparent;
    }

    .menu-item.active {
        background: rgba(64, 224, 208, 0.1);
        border: 1px solid rgba(64, 224, 208, 0.3);
    }
    
    .post-title {
        font-size: 0.85rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
    
    .menu-item .indicator {
        display: none;
    }
    
    .post-info {
        width: 100%;
        overflow: hidden; /* Mask the marquee */
    }

    @keyframes marquee-dynamic {
        0% { transform: translateX(0); opacity: 1; }
        15% { transform: translateX(0); opacity: 1; } /* Read start */
        65% { transform: translateX(calc(120px - 100%)); opacity: 1; } /* Scroll to end */
        85% { transform: translateX(calc(120px - 100%)); opacity: 1; } /* Hold end */
        90% { transform: translateX(calc(120px - 100%)); opacity: 0; } /* Fade out */
        90.1% { transform: translateX(0); opacity: 0; } /* Snap back while invisible */
        100% { transform: translateX(0); opacity: 1; } /* Fade in */
    }

    .menu-item.active .post-title.marquee {
        width: fit-content; 
        white-space: nowrap;
        overflow: visible;
        display: block; 
        max-width: none;
        animation: marquee-dynamic 6s linear infinite; 
        padding-right: 20px; 
    }
}
</style>
