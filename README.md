# Ownedge | Independent by Design

Live at: [https://ownedge.com](https://ownedge.com)

![Ownedge Preview](.github/preview.gif)

> **A digital window for independent creators and builders.**
> *Defying the establishment. Crafted with precision.*

---

## 🎨 Aesthetic & Design Philosophy

Ownedge.com is a love letter to **retro-futurism**, blending the raw utility of terminal interfaces with the sleek, high-fidelity visuals of modern web design along with retro visual cues of the 80's and 90's.

### Key Visual Elements
*   **CRT Simulation**: A multi-layered visual stack including scanlines, phosphor burn-in effect and noise (user-configurable) to simulate a high-quality CRT monitor.
*   **Light reflection**: The most bright content close to the edge, shines on the monitor bezel for a more realistic feel.
*   **VFD (Vacuum Fluorescent Display)**: Typography and color palettes (Teals/cyans against deep blacks) mimic the luminous glow of vintage VFD equipment with animations that remind classic pinball machines.
*   **Music Tracker Layout**: The top overlay is actually the background music track visualization like a music tracker (e.g., FastTracker II, Impulse Tracker), symbolizing the raw creation of art through code.
*   **Keyboard navigation**: The lost art of using the keyboard as a much more efficient user interface. You can virtually navigate to everything on the website using only the keyboard.

---

## 🏗️ Technical Architecture

Built on **Vue 3** and **Vite**, the application is architected for high performance despite the heavy visual load. It utilizes a **Flat-File CMS** approach where the filesystem itself is the database.

### 1. Hybrid Architecture (Vue + PHP API)
The project uses a clever hybrid approach to leverage standard shared hosting (LAMP stack) while delivering a modern SPA experience.

*   **Frontend**: A Vue 3 SPA that handles all UI, routing, and animations.
*   **Backend (`/public/api/`)**: Lightweight PHP scripts serve as the API layer.
    *   **`blog.php`**: Scans the filesystem for `.html` blog posts, parses metadata headers, and serves JSON lists. Also handles view counting and kudos.
    *   **`chat.php`**: A robust polling-based chat server supporting commands (`/nick`, `/me`), geolocation mapping, and ephemeral history.
    *   **`guestbook.php`**: Simple JSON-based storage for guestbook signatures.
*   **Data Storage**: All data (chat logs, stats, guestbook entries) is stored in flat JSON files within the `public/` directory, making the site strictly portable and database-free.

### 2. The Hack: Why PHP in 2026?
To leverage the domain's **free WordPress hosting**, we drop simple PHP scripts into a standard LAMP stack environment. This grants us persistent storage (JSON/txt files) and dynamic functionality without paying for a VPS or cloud functions. It's a pragmatic nod to the "old web"—simple, robust, and cost-efficient.

### 3. Audio Engine (`SoundManager`)
The application features a custom audio subsystem capable of real-time sound synthesis and interaction.
*   **WASM Backend**: Heavy pattern processing for the tracker lines is offloaded to a WebAssembly module to ensure the main thread remains free for UI rendering.
*   **Visualization**: The `BootLoader` component taps directly into the audio frequency data to drive the real-time spectrum analyzer on the "VFD" screen.

### 4. Visual System
*   **`BootLoader.vue`**: A complex state machine managing the startup sequence (BIOS -> Memory Test -> Kernel Load -> Connection). It uses efficient DOM updates to simulate a high-speed terminal interface.
*   **`TrackerOverlay.vue`**: Optimized canvas renderer for the music visualization overlay.
*   **`ChatSection.vue`**: Features a canvas-based **GeoMap** visualization that renders connected users as glowing dots on a high-density world map based on their IP geolocation.

### 5. SEO & Static Generation
*   **Deep Linking**: The build process (`generate-static-routes.js`) scans the blog content directory and physically generates static HTML entry points for every post (e.g., `/blog/15-years/index.html`).
*   **Meta Injection**: Correct OpenGraph and Twitter card metadata is injected into these static files, ensuring reliable social sharing and SEO indexing for dynamic content.

---

## 🛠️ Development

### Prerequisites
*   Node.js 18+
*   npm

### Setup
```bash
npm install
```

### Run Locally
Start the high-performance Vite development server.
*   **Note**: The local server is configured to **proxy API requests** (`/api/...`) directly to the production server (`ownedge.com`). This means you see live chat and stats even while developing locally.
```bash
npm run dev
```

### Build for Production
Generates the static bundle and handles static route generation:
```bash
npm run build
```

---

## 📂 Project Structure

*   `src/components`: UI components (BootLoader, TrackerOverlay, BlogSection, etc.)
*   `src/sfx`: Specialized sound management logic.
*   `src/store`: Reactive state management (ChatStore).
*   `public/api/`: Backend PHP scripts (`chat.php`, `blog.php`, `guestbook.php`).
*   `public/blog/`: Flat-file blog content (HTML files with metadata headers).
*   `generate-static-routes.js`: Build script for SEO and sitemap generation.
