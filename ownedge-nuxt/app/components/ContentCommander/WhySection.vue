<template>
  <div class="section-content animate-in">
    <h3>> WHY</h3>
    
    <div class="manifesto-wrapper">
      <div class="manifesto-content">
        <p class="highlight">Ownedge was created from a simple idea: that the most meaningful work happens when you care deeply about what you build — and when you choose to stand behind it.</p>
        
        <p>We live in a world full of fast launches, disposable products, and short-term thinking. Ownedge exists as a quiet counterweight to that. It is a place to build deliberately and with craftsmanship — whether that means software, infrastructure, products, or real-world assets.</p>
        
        <p>At its core, Ownedge is about ownership in the deepest sense: not just legal ownership, but intellectual ownership, responsibility, and pride in what exists because you made it.</p>
        
        <p>We design systems the way artisans shape objects: with attention to detail, structure, balance, and longevity. </p>

        <p>Our work is not about chasing trends — <span>We set the trends.</span></p>
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
    font-size: 1.4rem;
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
    font-size: 1.2rem;
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
</style>
