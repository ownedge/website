import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const distPath = path.join(__dirname, 'dist');
const indexPath = path.join(distPath, 'index.html');

// Define your application routes (matching App.vue)
const routes = [
    'what',
    'why',
    'guestbook',
    'chat'
];
const baseUrl = 'https://ownedge.com';

if (!fs.existsSync(indexPath)) {
    console.error('Error: dist/index.html not found. Run "npm run build" first.');
    process.exit(1);
}

const indexContent = fs.readFileSync(indexPath, 'utf8');

const metadata = {
    what: { title: "Ownedge | What We Do", description: "Exploring the boundaries of digital products, strategy, and engineering." },
    why: { title: "Ownedge | Why We Exist", description: "The Ownedge manifesto: our vision for a more intentional, independent digital future." },
    guestbook: { title: "Ownedge | Leave Your Mark", description: "Sign the guestbook and join the lineage of terminal users." },
    chat: { title: "Ownedge | Terminal Cluster", description: "Communicate in real-time with other nodes connected to the Ownedge cluster." }
};

const fallbackContent = {
    what: `
      <main class="seo-content">
        <h1>Ownedge</h1>
        <h2>What We Do</h2>
        <p>Exploring the boundaries of digital products, strategy, and engineering.</p>
        <nav aria-label="Primary">
          <ul>
            <li><a href="/what">What</a></li>
            <li><a href="/why">Why</a></li>
            <li><a href="/guestbook">Guestbook</a></li>
            <li><a href="/chat">Chat</a></li>
          </ul>
        </nav>
      </main>
    `.trim(),
    why: `
      <main class="seo-content">
        <h1>Ownedge</h1>
        <h2>Why We Exist</h2>
        <p>The Ownedge manifesto: our vision for a more intentional, independent digital future.</p>
        <nav aria-label="Primary">
          <ul>
            <li><a href="/what">What</a></li>
            <li><a href="/why">Why</a></li>
            <li><a href="/guestbook">Guestbook</a></li>
            <li><a href="/chat">Chat</a></li>
          </ul>
        </nav>
      </main>
    `.trim(),
    guestbook: `
      <main class="seo-content">
        <h1>Ownedge</h1>
        <h2>Guestbook</h2>
        <p>Sign the guestbook and join the lineage of terminal users.</p>
        <nav aria-label="Primary">
          <ul>
            <li><a href="/what">What</a></li>
            <li><a href="/why">Why</a></li>
            <li><a href="/guestbook">Guestbook</a></li>
            <li><a href="/chat">Chat</a></li>
          </ul>
        </nav>
      </main>
    `.trim(),
    chat: `
      <main class="seo-content">
        <h1>Ownedge</h1>
        <h2>Chat</h2>
        <p>Communicate in real-time with other nodes connected to the Ownedge cluster.</p>
        <nav aria-label="Primary">
          <ul>
            <li><a href="/what">What</a></li>
            <li><a href="/why">Why</a></li>
            <li><a href="/guestbook">Guestbook</a></li>
            <li><a href="/chat">Chat</a></li>
          </ul>
        </nav>
      </main>
    `.trim()
};

routes.forEach(route => {
    const routeDir = path.join(distPath, route);
    
    // Create directory (e.g., dist/why/)
    if (!fs.existsSync(routeDir)) {
        fs.mkdirSync(routeDir, { recursive: true });
    }
    
    // Inject metadata
    let content = indexContent;
    const data = metadata[route];
    if (data) {
        content = content.replace(/<title>.*?<\/title>/, `<title>${data.title}</title>`);
        content = content.replace(/<meta name="description" content=".*?" \/>/, `<meta name="description" content="${data.description}" />`);
        content = content.replace(/<meta property="og:title" content=".*?" \/>/, `<meta property="og:title" content="${data.title}" />`);
        content = content.replace(/<meta property="og:description" content=".*?" \/>/, `<meta property="og:description" content="${data.description}" />`);
        content = content.replace(/<meta property="twitter:title" content=".*?" \/>/, `<meta property="twitter:title" content="${data.title}" />`);
        content = content.replace(/<meta property="twitter:description" content=".*?" \/>/, `<meta property="twitter:description" content="${data.description}" />`);
        
        // Update URLs
        content = content.replace(/<link rel="canonical" href=".*?" \/>/, `<link rel="canonical" href="https://ownedge.com/${route}" />`);
        content = content.replace(/<meta property="og:url" content=".*?" \/>/, `<meta property="og:url" content="https://ownedge.com/${route}" />`);
        content = content.replace(/<meta property="twitter:url" content=".*?" \/>/, `<meta property="twitter:url" content="https://ownedge.com/${route}" />`);
    }

    const fallback = fallbackContent[route];
    if (fallback) {
        content = content.replace(/<main class="seo-content">[\s\S]*?<\/main>/, fallback);
    }

    // Write index.html to the directory
    // This allows Nginx to find /why/index.html when /why is requested
    const targetPath = path.join(routeDir, 'index.html');
    fs.writeFileSync(targetPath, content);
    
    console.log(`Generated static route: /${route}/index.html with metadata`);
});

// Generate Sitemap.xml
const sitemapContent = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>${baseUrl}/</loc>
    <lastmod>${new Date().toISOString().split('T')[0]}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>1.0</priority>
  </url>
${routes.map(route => `  <url>
    <loc>${baseUrl}/${route}</loc>
    <lastmod>${new Date().toISOString().split('T')[0]}</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>`).join('\n')}
</urlset>`;

fs.writeFileSync(path.join(distPath, 'sitemap.xml'), sitemapContent);
console.log('Generated sitemap.xml 🗺️');

// Generate Robots.txt
const robotsContent = `User-agent: *
Allow: /

Sitemap: ${baseUrl}/sitemap.xml
`;
fs.writeFileSync(path.join(distPath, 'robots.txt'), robotsContent);
console.log('Generated robots.txt 🤖');

console.log('Static route generation complete! 🦾');
