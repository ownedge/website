# Migration Plan: Vite+Vue to Nuxt.js

## 1. Project Initialization
- [ ] Initialize a new Nuxt project in a separate directory or branch to avoid breaking the current site.
- [ ] Install dependencies: `sass` (if used), `pinia` (for state management), `three` (if used directly), and any other libs from `package.json`.
- [ ] Configure `nuxt.config.ts`:
    - Enable SSR/SSG.
    - Configure global CSS/SCSS.
    - Setup Head management (meta tags).

## 2. Component Migration
- [ ] **Move Components**: Copy `src/components` to Nuxt `components/`.
    - Nuxt auto-imports components, so explicit imports in script setup can be removed.
- [ ] **Assets**: Move `public/` and `src/assets/` to Nuxt `public/` and `assets/`.
    - Update image paths (Nuxt uses `~/assets` for processed assets, `/` for static).

## 3. Architecture & State Management (The Big Shift)
- [ ] **Layouts (`layouts/default.vue`)**:
    - This is where `App.vue` logic largely goes.
    - **Persistent Elements**: `BootLoader`, `GridOverlay`, `TrackerOverlay`, `CrtControls`, `VfdDisplay`, `BezelReflection` live here.
    - **Logic**: Move the huge `App.vue` script (cursor tracking, CRT noise, Audio init) here to ensure it persists across page navigations.
    - Use `<slot />` or `<NuxtPage />` for the dynamic content area.
- [ ] **State (Pinia)**:
    - Create stores (`stores/crt.js`, `stores/audio.js`) to replace the `ref`s currently holding global state in `App.vue` (like `isBooted`, `volume`, `brightness`).

## 4. Routing & Pages (`pages/`)
- [ ] **Dynamic Routes**: Create `pages/index.vue`, `pages/what.vue`, `pages/why.vue`, `pages/guestbook.vue`, `pages/chat.vue`.
- [ ] **Content Extraction**:
    - Move content from `HeroDisplay.vue` -> `pages/index.vue`.
    - Move content from `ContentCommander` sections -> respective `pages/*.vue` files.
- [ ] **Navigation**:
    - Refactor `ContentCommander` tab logic to use `<NuxtLink>` or `router.push()`.
    - Ensure the "TUI" tab feel remains even though the URL changes.

## 5. Client-Side Specifics (SSR Handling)
- [ ] **Audio & Canvas**:
    - Wrap `SoundManager` initialization in `onMounted` or `clientOnly` plugins.
    - Ensure `canvas` refs are accessed only on the client.
- [ ] **Browser APIs**: Check for `window` or `document` usage and guard with `if (process.client)`.

## 6. SEO & Metadata
- [ ] **Remove `generate-static-routes.js`**.
- [ ] Use `useHead()` in each page file to define unique titles and meta descriptions.
- [ ] Test `nuxt generate` to ensure static HTML is built correctly for crawlers.

## 7. Migration Steps
1. **Scaffold**: Run `npx nuxi@latest init ownedge-nuxt`.
2. **Assets**: Port CSS and Images.
3. **Layout**: Port `App.vue` logic to `default.vue` layout.
4. **Pages**: Create page files and verify navigation.
5. **State**: Implement Pinia.
6. **Polish**: Check CRT effects, Audio, and specific "glitch" behaviors.
