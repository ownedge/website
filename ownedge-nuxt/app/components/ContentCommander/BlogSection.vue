<template>
  <div class="section-content animate-in">
    <h3>> LOGS.LOG</h3>
    <div class="blog-list">
      <div class="blog-entry">
        <span class="entry-date">2024.12.28</span>
        <span class="entry-title">CRT SHADER OPTIMIZATION COMPLETE</span>
      </div>
      <div class="blog-entry">
        <span class="entry-date">2024.12.15</span>
        <span class="entry-title">MIGRATING TO OWNEDGE-V2 CORE</span>
      </div>
      <div class="blog-entry">
        <span class="entry-date">2024.11.30</span>
        <span class="entry-title">PHOSPHOR DECAY LOGIC REFINED</span>
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
}

.blog-entry {
    margin-bottom: 15px;
    padding: 10px;
    border-left: 3px solid var(--color-accent);
    background: rgba(64, 224, 208, 0.05);
}

.entry-date { display: block; font-size: 0.8rem; opacity: 0.5; }
.entry-title { font-weight: bold; }

.animate-in {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 900px) {
    .section-content h3 {
        font-size: 1.1rem;
        margin-bottom: 15px;
    }
}
</style>
