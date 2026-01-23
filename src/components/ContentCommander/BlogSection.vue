<script setup>
import { ref, onMounted } from 'vue';
import SoundManager from '../../sfx/SoundManager';

const posts = ref([]);
const selectedPost = ref(null);
const isLoading = ref(true);

const isProd = import.meta.env.PROD;
const API_BASE = isProd ? '/blog.php' : 'https://ownedge.com/blog.php';

const formatDate = (dateStr) => {
    return new Date(dateStr).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    }).toUpperCase();
};

const fetchList = async () => {
    // Local Fallback Data (Matches PHP Seed)
    const localSeeds = [
        { id: 'manifesto', title: 'THE INDEPENDENT WEB', date: '2025-05-12T14:00:00Z', views: 1240, kudos: 42 },
        { id: 'terminal-aesthetics', title: 'TERMINAL AESTHETICS', date: '2025-06-20T09:30:00Z', views: 890, kudos: 28 },
        { id: 'hello-world', title: 'HELLO WORLD', date: '2025-09-01T12:00:00Z', views: 1, kudos: 0 }
    ];

    try {
        const res = await fetch(API_BASE);
        if (res.ok) {
            posts.value = await res.json();
        } else {
             throw new Error('API Error');
        }
    } catch (e) {
        console.warn('API Fetch failed, using local fallback:', e);
        if (!isProd) {
            // In Dev, falling back to local seed allows UI testing
            posts.value = localSeeds;
        }
    } finally {
        isLoading.value = false;
    }
};

const selectPost = async (id) => {
    SoundManager.playTypewriterReturnSound();
    selectedPost.value = { loading: true };
    
    // DEV MODE: Load locally from public folder to allow preview without PHP
    if (!isProd) {
        try {
            const htmlRes = await fetch(`/blog/${id}.html`);
            if (htmlRes.ok) {
                const html = await htmlRes.text();
                // Find metadata from the already loaded list
                const meta = posts.value.find(p => p.id === id) || { title: 'LOCAL PREVIEW', date: new Date(), views: 0, kudos: 0 };
                
                // Simulate network delay for effect
                setTimeout(() => {
                    selectedPost.value = { 
                        ...meta, 
                        content: html,
                        // If metadata missing from remote list, use defaults
                        views: meta.views || 0,
                        kudos: meta.kudos || 0
                    };
                }, 300);
                return;
            }
        } catch (e) {
            console.warn("Local fetch failed, trying API fallback...");
        }
    }

    // PROD MODE (or Fallback): Use PHP Backend
    try {
        const res = await fetch(`${API_BASE}?id=${id}`);
        if (res.ok) {
            selectedPost.value = await res.json();
        } else {
             selectedPost.value = null; // Error state could be better handling
        }
    } catch (e) {
        selectedPost.value = null;
    }
};

const closePost = () => {
    SoundManager.playHoverSound();
    selectedPost.value = null;
};

const giveKudos = async (id) => {
    if (!selectedPost.value) return;
    
    // Optimistic update
    selectedPost.value.kudos++;
    SoundManager.playTypingSound();

    try {
        await fetch(`${API_BASE}?id=${id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'kudos' })
        });
        
        // Update list view too
        const inList = posts.value.find(p => p.id === id);
        if (inList) inList.kudos = selectedPost.value.kudos;
        
    } catch (e) {}
};

onMounted(() => {
    fetchList();
});
</script>

<template>
  <div class="section-content animate-in">
    <h3>> LOGS</h3>
    
    <div class="blog-layout">
        <!-- LEFT: Manifest List -->
        <div class="blog-menu">
             <div v-if="isLoading" class="loading-text">SEARCHING...</div>
             <div 
                v-else
                v-for="post in posts" 
                :key="post.id" 
                class="log-item"
                :class="{ active: selectedPost && selectedPost.id === post.id }"
                @click="selectPost(post.id)"
             >
                <div class="log-info">
                    <span class="log-title">{{ post.title }}</span>
                    <span class="log-date">{{ formatDate(post.date) }}</span>
                </div>
                <span class="indicator">></span>
             </div>
        </div>

        <!-- RIGHT: Terminal Output -->
        <div class="blog-viewport custom-scroll">
            <transition name="fade" mode="out-in">
                <div v-if="selectedPost" :key="selectedPost.id" class="read-pane">
                    <div v-if="selectedPost.loading" class="loading-text">RETRIEVING DATA BLOCKS...</div>
                    <template v-else>
                        <div class="read-header">
                            <span class="read-date">{{ formatDate(selectedPost.date) }}</span>
                            <div class="read-stats">
                                <span>Views: {{ selectedPost.views }}</span>
                            </div>
                        </div>
                        
                        <h4 class="read-title">{{ selectedPost.title }}</h4>
                        
                        <div class="post-content" v-html="selectedPost.content"></div>
                        
                        <div class="read-footer">
                             <button class="kudos-btn" @click="giveKudos(selectedPost.id)">
                                STAR LOG [ {{ selectedPost.kudos }} ]
                            </button>
                            <span class="log-id">ID: {{ selectedPost.id }}</span>
                        </div>
                    </template>
                </div>
                
                <div v-else class="empty-state">
                    <p>SELECT A LOG ENTRY TO DECRYPT...</p>
                </div>
            </transition>
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
    font-size: 1.4rem;
    letter-spacing: 1px;
}

.blog-layout {
    display: grid;
    grid-template-columns: 240px 1fr;
    gap: 40px;
    height: calc(100% - 60px); /* Fill remaining vertical space */
    min-height: 400px;
}

/* LEFT MENU */
.blog-menu {
    border-right: 1px solid rgba(255,255,255,0.1);
    padding-right: 20px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    overflow-y: auto;
}

.log-item {
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 10px;
    border-radius: 4px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.log-item:hover {
    background: rgba(255,255,255,0.03);
}

.log-item.active {
    background: rgba(64, 224, 208, 0.1);
    border: 1px solid rgba(64, 224, 208, 0.3);
}

.log-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.log-title {
    font-weight: bold;
    font-size: 0.9rem;
    color: #ccc;
}
.log-item.active .log-title { color: #fff; }

.log-date {
    font-size: 0.75rem;
    color: #666;
    font-family: 'JetBrains Mono', monospace;
}
.log-item.active .log-date { color: var(--color-accent); }

.indicator {
    opacity: 0;
    color: var(--color-accent);
    font-weight: bold;
}
.log-item.active .indicator { opacity: 1; }

/* RIGHT VIEWPORT */
.blog-viewport {
    position: relative;
    padding-right: 10px;
    overflow-y: auto;
}

.read-pane {
    animation: fadeIn 0.3s ease;
}

.read-header {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 5px;
    font-family: 'JetBrains Mono', monospace;
}

.read-title {
    font-size: 2rem;
    margin: 0 0 20px 0;
    color: var(--color-accent);
    line-height: 1.1;
    text-transform: uppercase;
}

.post-content {
    font-size: 1.1rem;
    line-height: 1.6;
    color: rgba(255,255,255,0.85);
    margin-bottom: 40px;
}
/* Deep selector for v-html content */
:deep(.post-content h2) {
    color: #fff;
    margin-top: 30px;
    font-size: 1.4rem;
}
:deep(.post-content strong) { color: #fff; }
:deep(.post-content .quote-block) {
    border-left: 3px solid var(--color-accent);
    padding-left: 20px;
    margin: 20px 0;
    font-style: italic;
    color: var(--color-accent);
}
:deep(.post-content .ascii-art) {
    font-family: 'JetBrains Mono', monospace;
    white-space: pre;
    line-height: 1.2;
    overflow-x: auto;
    color: var(--color-accent);
    margin: 20px 0;
    opacity: 0.8;
}

.read-footer {
    border-top: 1px solid rgba(255,255,255,0.1);
    padding-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.kudos-btn {
    background: transparent;
    border: 1px solid #444;
    color: #FFD700;
    padding: 8px 16px;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.9rem;
}
.kudos-btn:hover {
    border-color: #FFD700;
    box-shadow: 0 0 10px rgba(255, 215, 0, 0.2);
}

.log-id { font-size: 0.75rem; color: #444; }

.empty-state {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #444;
    font-style: italic;
}

.animate-in { animation: slideUp 0.3s ease-out; }
@keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: 0; } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

/* Mobile */
@media (max-width: 900px) {
    .blog-layout {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr;
        gap: 20px;
    }
    
    .blog-menu {
        max-height: 150px;
        border-right: none;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding-bottom: 15px;
    }
}
</style>
