import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
import fs from 'fs';
import path from 'path';

export default defineConfig({
  plugins: [
    vue(),
    {
      name: 'php-mock-server',
      configureServer(server) {
        server.middlewares.use((req, res, next) => {
          if (req.url.startsWith('/api/blog.php')) {
            const url = new URL(req.url, `http://${req.headers.host}`);
            const action = url.searchParams.get('action') || 'list';
            const blogDir = path.resolve(__dirname, 'public/blog');
            const statsFile = path.join(blogDir, 'stats.json');

            // Helper: Get Stats
            const getStats = () => {
              if (fs.existsSync(statsFile)) {
                return JSON.parse(fs.readFileSync(statsFile, 'utf-8') || '{}');
              }
              return {};
            };

            // Helper: Save Stats
            const saveStats = (stats) => {
              fs.writeFileSync(statsFile, JSON.stringify(stats, null, 2));
            };

            res.setHeader('Content-Type', 'application/json');

            if (action === 'list') {
              if (!fs.existsSync(blogDir)) {
                  res.end('[]');
                  return;
              }
              const files = fs.readdirSync(blogDir).filter(f => f.endsWith('.html'));
              const posts = [];

              files.forEach(file => {
                const content = fs.readFileSync(path.join(blogDir, file), 'utf-8');
                const match = content.match(/<!--\s*::metadata::(.*?)::\/metadata::\s*-->/s);
                
                if (match) {
                  const meta = { file };
                  match[1].split('\n').forEach(line => {
                    const parts = line.split(':');
                    if (parts.length >= 2) {
                      const key = parts.shift().trim();
                      const value = parts.join(':').trim();
                      if (key) meta[key] = value;
                    }
                  });
                  if (meta.id && meta.title) posts.push(meta);
                }
              });

              // Sort descending by date
              posts.sort((a, b) => new Date(b.date) - new Date(a.date));
              res.end(JSON.stringify(posts));
              return;
            }

            if (action === 'stats') {
              const id = url.searchParams.get('id');
              const stats = getStats();
              const postStats = stats[id] || { views: 0, kudos: 0 };
              res.end(JSON.stringify(postStats));
              return;
            }

            if (action === 'view' || action === 'kudo') {
              const id = url.searchParams.get('id');
              if (!id) {
                 res.end(JSON.stringify({ error: 'Missing ID' }));
                 return;
              }
              const stats = getStats();
              if (!stats[id]) stats[id] = { views: 0, kudos: 0 };
              
              if (action === 'view') stats[id].views++;
              if (action === 'kudo') stats[id].kudos++;
              
              saveStats(stats);
              res.end(JSON.stringify(stats[id]));
              return;
            }

            res.end(JSON.stringify({ error: 'Invalid action' }));
            return;
          }
          next();
        });
      }
    }
  ],
  server: {
    proxy: {
      '/api': {
        target: 'https://ownedge.com',
        changeOrigin: true,
        secure: false, // In case of self-signed certs or issues
      }
    }
  }
})
