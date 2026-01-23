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
    try {
        const res = await fetch(API_BASE);
        if (res.ok) {
            posts.value = await res.json();
            isLoading.value = false;
        }
    } catch (e) {
        console.error(e);
        isLoading.value = false;
    }
};

const selectPost = async (id) => {
    SoundManager.playTypewriterReturnSound();
    selectedPost.value = { loading: true };
    try {
        const res = await fetch(`${API_BASE}?id=${id}`);
        if (res.ok) {
            selectedPost.value = await res.json();
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
  <div class="blog-container">
    <transition name="fade-slide" mode="out-in">
        <!-- LIST VIEW -->
        <div v-if="!selectedPost" class="post-list" key="list">
             <div class="header-row">
                 <span>DATE</span>
                 <span>TITLE</span>
                 <span>P.ID</span>
                 <span>STATS</span>
             </div>
             <div class="divider">========================================================================</div>
             
             <div v-if="isLoading" class="loading">SEARCHING ARCHIVES...</div>
             
             <div 
                v-for="(post, i) in posts" 
                :key="post.id" 
                class="post-row" 
                @click="selectPost(post.id)"
             >
                 <div class="col date">{{ formatDate(post.date) }}</div>
                 <div class="col title">{{ post.title }}</div>
                 <div class="col id">LOG-{{ String(i+1).padStart(3, '0') }}</div>
                 <div class="col stats">
                     <span class="view-count">👁 {{ post.views }}</span>
                     <span class="kudo-count">★ {{ post.kudos }}</span>
                 </div>
             </div>
             
             <div class="divider">========================================================================</div>
             <div class="status-line">{{ posts.length }} ENTRIES FOUND.</div>
        </div>

        <!-- DETAIL VIEW -->
        <div v-else class="post-detail" key="detail">
            <div v-if="selectedPost.loading" class="loading">RETRIEVING DATA BLOCKS...</div>
            <template v-else>
                <div class="detail-header">
                    <button class="back-btn" @click="closePost">[ BACK TO INDEX ]</button>
                    <div class="meta-info">
                        <span>{{ formatDate(selectedPost.date) }}</span>
                        <span>Views: {{ selectedPost.views }}</span>
                    </div>
                </div>
                
                <h1 class="post-title">{{ selectedPost.title }}</h1>
                <div class="divider">------------------------------------------------------------</div>
                
                <div class="post-content">
                    <p v-for="(para, i) in selectedPost.content.split('\n\n')" :key="i">
                        {{ para }}
                    </p>
                </div>
                
                <div class="detail-footer">
                    <button class="kudos-btn" @click="giveKudos(selectedPost.id)">
                        [ KUDOS: {{ selectedPost.kudos }} ]
                    </button>
                    <div class="id-tag">ID: {{ selectedPost.id }}</div>
                </div>
            </template>
        </div>
    </transition>
  </div>
</template>

<style scoped>
.blog-container {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.divider {
    color: #444;
    margin: 10px 0;
    font-size: 0.8rem;
    overflow: hidden;
    white-space: nowrap;
}

/* List Styles */
.header-row {
    display: flex;
    font-size: 0.85rem;
    color: var(--color-accent);
    opacity: 0.7;
    padding: 0 10px;
}

.header-row span {
    flex: 1;
}
.header-row span:first-child { flex: 0 0 120px; }
.header-row span:nth-child(2) { flex: 2; }
.header-row span:last-child { text-align: right; }

.post-row {
    display: flex;
    padding: 12px 10px;
    cursor: pointer;
    border-left: 2px solid transparent;
    transition: all 0.2s;
}

.post-row:hover {
    background: rgba(64, 224, 208, 0.1);
    border-left: 2px solid var(--color-accent);
    color: #fff;
}

.col {
    font-size: 0.95rem;
}

.col.date {
    flex: 0 0 120px;
    color: #888;
}

.col.title {
    flex: 2;
    font-weight: bold;
    color: var(--color-accent);
}

.col.id {
    flex: 1;
    color: #555;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
}

.col.stats {
    flex: 1;
    text-align: right;
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    color: #666;
    font-size: 0.85rem;
}

.loading, .status-line {
    padding: 20px;
    text-align: center;
    color: #666;
    font-style: italic;
}

/* Detail Styles */
.detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.back-btn {
    background: transparent;
    border: 1px solid var(--color-accent);
    color: var(--color-accent);
    padding: 8px 15px;
    font-family: inherit;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.back-btn:hover {
    background: var(--color-accent);
    color: #000;
}

.meta-info {
    color: #666;
    font-size: 0.9rem;
    display: flex;
    gap: 20px;
}

.post-title {
    font-size: 1.8rem;
    color: #fff;
    margin: 0 0 10px 0;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.post-content {
    line-height: 1.6;
    color: #ccc;
    font-size: 1.1rem;
    margin: 30px 0;
    white-space: pre-wrap;
    max-width: 800px;
}

.detail-footer {
    margin-top: 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid #333;
    padding-top: 20px;
}

.kudos-btn {
    background: transparent;
    border: none;
    color: #ffd700;
    font-family: inherit;
    font-size: 1.1rem;
    cursor: pointer;
    transition: transform 0.1s;
}

.kudos-btn:hover {
    transform: scale(1.05);
    text-shadow: 0 0 10px #ffd700;
}

.kudos-btn:active {
    transform: scale(0.95);
}

.id-tag {
    color: #444;
    font-size: 0.8rem;
}

/* Animation */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.3s ease;
}

.fade-slide-enter-from {
  opacity: 0;
  transform: translateX(20px);
}

.fade-slide-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}

@media (max-width: 700px) {
    .header-row { display: none; }
    .col.date { display: none; }
    .col.id { display: none; }
    .col.title { font-size: 1.1rem; }
    
    .post-row {
        flex-direction: column;
        gap: 5px;
        border-bottom: 1px solid #222;
    }
    
    .col.stats {
        justify-content: flex-start;
        font-size: 0.8rem;
    }
}
</style>
