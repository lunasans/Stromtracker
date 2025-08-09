// sw.js - Service Worker für Stromtracker PWA

const CACHE_NAME = 'stromtracker-v2.0.0';
const STATIC_CACHE = 'stromtracker-static-v2.0.0';
const DYNAMIC_CACHE = 'stromtracker-dynamic-v2.0.0';

// Assets die immer gecacht werden sollen
const STATIC_ASSETS = [
    '/',
    '/dashboard.php',
    '/zaehlerstand.php',
    '/geraete.php',
    '/auswertung.php',
    '/tarife.php',
    '/css/style.css',
    '/css/modern-styles.css',
    '/js/main.js',
    '/js/modern-features.js',
    '/includes/header-modern.php',
    '/includes/footer-modern.php',
    '/manifest.json',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js',
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js'
];

// URLs die nie gecacht werden sollen
const NEVER_CACHE = [
    '/logout.php',
    '/api/',
    '/admin/',
    'chrome-extension://'
];

// ========================================
// Installation
// ========================================
self.addEventListener('install', event => {
    console.log('[SW] Installing Service Worker');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('[SW] Static assets cached');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('[SW] Failed to cache static assets:', error);
            })
    );
});

// ========================================
// Aktivierung
// ========================================
self.addEventListener('activate', event => {
    console.log('[SW] Activating Service Worker');
    
    event.waitUntil(
        Promise.all([
            // Alte Caches löschen
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('[SW] Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            }),
            // Alle Clients übernehmen
            self.clients.claim()
        ])
    );
});

// ========================================
// Fetch Handler (Caching Strategy)
// ========================================
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip URLs that should never be cached
    if (NEVER_CACHE.some(pattern => url.pathname.includes(pattern))) {
        return;
    }
    
    // Handle different types of requests
    if (url.origin === location.origin) {
        // Same-origin requests (PHP files, assets)
        event.respondWith(handleSameOriginRequest(request));
    } else {
        // Cross-origin requests (CDN, APIs)
        event.respondWith(handleCrossOriginRequest(request));
    }
});

// ========================================
// Same-Origin Request Handler
// ========================================
async function handleSameOriginRequest(request) {
    const url = new URL(request.url);
    
    try {
        // Cache-First für statische Assets
        if (isStaticAsset(url.pathname)) {
            return await cacheFirst(request);
        }
        
        // Network-First für PHP-Seiten
        if (url.pathname.endsWith('.php') || url.pathname === '/') {
            return await networkFirst(request);
        }
        
        // Stale-While-Revalidate für andere Ressourcen
        return await staleWhileRevalidate(request);
        
    } catch (error) {
        console.error('[SW] Request failed:', error);
        return await getOfflinePage();
    }
}

// ========================================
// Cross-Origin Request Handler
// ========================================
async function handleCrossOriginRequest(request) {
    try {
        // Cache-First für CDN-Ressourcen
        return await cacheFirst(request);
    } catch (error) {
        console.error('[SW] Cross-origin request failed:', error);
        // Fallback für kritische Ressourcen
        return new Response('', { status: 204 });
    }
}

// ========================================
// Caching Strategies
// ========================================

// Cache-First: Cache zuerst, dann Network
async function cacheFirst(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cached = await cache.match(request);
    
    if (cached) {
        console.log('[SW] Serving from cache:', request.url);
        return cached;
    }
    
    const response = await fetch(request);
    
    if (response.status === 200) {
        cache.put(request, response.clone());
        console.log('[SW] Cached new resource:', request.url);
    }
    
    return response;
}

// Network-First: Network zuerst, dann Cache
async function networkFirst(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    
    try {
        const response = await fetch(request);
        
        if (response.status === 200) {
            cache.put(request, response.clone());
            console.log('[SW] Updated cache:', request.url);
        }
        
        return response;
        
    } catch (error) {
        console.log('[SW] Network failed, serving from cache:', request.url);
        const cached = await cache.match(request);
        
        if (cached) {
            return cached;
        }
        
        // Offline-Seite für HTML-Requests
        if (request.headers.get('Accept')?.includes('text/html')) {
            return await getOfflinePage();
        }
        
        throw error;
    }
}

// Stale-While-Revalidate: Cache sofort, Update im Hintergrund
async function staleWhileRevalidate(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    const cached = await cache.match(request);
    
    // Fetch im Hintergrund
    const fetchPromise = fetch(request).then(response => {
        if (response.status === 200) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(error => {
        console.error('[SW] Background fetch failed:', error);
    });
    
    // Cache sofort zurückgeben oder auf Network warten
    return cached || await fetchPromise;
}

// ========================================
// Helper Functions
// ========================================

function isStaticAsset(pathname) {
    const staticExtensions = ['.css', '.js', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.ico', '.woff', '.woff2'];
    return staticExtensions.some(ext => pathname.endsWith(ext));
}

async function getOfflinePage() {
    try {
        const cache = await caches.open(STATIC_CACHE);
        const offlinePage = await cache.match('/offline.html');
        
        if (offlinePage) {
            return offlinePage;
        }
        
        // Fallback HTML
        return new Response(`
            <!DOCTYPE html>
            <html lang="de">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Offline - Stromtracker</title>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        color: white;
                        margin: 0;
                        padding: 20px;
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        text-align: center;
                    }
                    .container {
                        max-width: 400px;
                    }
                    .energy-indicator {
                        width: 60px;
                        height: 60px;
                        border-radius: 50%;
                        background: #eab308;
                        margin: 0 auto 20px;
                        animation: pulse 2s infinite;
                    }
                    @keyframes pulse {
                        0% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0.7); }
                        70% { box-shadow: 0 0 0 20px rgba(234, 179, 8, 0); }
                        100% { box-shadow: 0 0 0 0 rgba(234, 179, 8, 0); }
                    }
                    .btn {
                        background: white;
                        color: #333;
                        padding: 12px 24px;
                        border: none;
                        border-radius: 25px;
                        text-decoration: none;
                        display: inline-block;
                        margin-top: 20px;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="energy-indicator"></div>
                    <h1>⚡ Stromtracker</h1>
                    <h2>Sie sind offline</h2>
                    <p>Keine Internetverbindung verfügbar. Einige Funktionen sind möglicherweise eingeschränkt.</p>
                    <button class="btn" onclick="window.location.reload()">
                        Erneut versuchen
                    </button>
                </div>
            </body>
            </html>
        `, {
            headers: { 'Content-Type': 'text/html' }
        });
        
    } catch (error) {
        console.error('[SW] Failed to get offline page:', error);
        return new Response('Offline', { status: 503 });
    }
}

// ========================================
// Background Sync (für offline Daten)
// ========================================
self.addEventListener('sync', event => {
    if (event.tag === 'background-sync-readings') {
        console.log('[SW] Background sync: readings');
        event.waitUntil(syncReadings());
    }
});

async function syncReadings() {
    try {
        // Offline gespeicherte Zählerstände hochladen
        const offlineReadings = await getOfflineReadings();
        
        for (const reading of offlineReadings) {
            await uploadReading(reading);
        }
        
        await clearOfflineReadings();
        console.log('[SW] Offline readings synced');
        
    } catch (error) {
        console.error('[SW] Background sync failed:', error);
    }
}

async function getOfflineReadings() {
    // Implementation für offline Datenspeicherung
    // IndexedDB oder andere Speichermethode
    return [];
}

async function uploadReading(reading) {
    // Implementation für Upload zur Server-API
    return fetch('/api/readings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(reading)
    });
}

async function clearOfflineReadings() {
    // Implementation zum Löschen der offline Daten
}

// ========================================
// Push Notifications
// ========================================
self.addEventListener('push', event => {
    if (!event.data) return;
    
    const data = event.data.json();
    
    const options = {
        body: data.body || 'Neue Benachrichtigung von Stromtracker',
        icon: '/icons/icon-192x192.png',
        badge: '/icons/badge-72x72.png',
        tag: data.tag || 'stromtracker-notification',
        vibrate: [200, 100, 200],
        actions: [
            {
                action: 'view',
                title: 'Anzeigen',
                icon: '/icons/action-view.png'
            },
            {
                action: 'dismiss',
                title: 'Schließen',
                icon: '/icons/action-close.png'
            }
        ],
        data: data
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'Stromtracker', options)
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.action === 'view') {
        const url = event.notification.data?.url || '/dashboard.php';
        event.waitUntil(
            self.clients.openWindow(url)
        );
    }
});

// ========================================
// Error Handling
// ========================================
self.addEventListener('error', event => {
    console.error('[SW] Service Worker error:', event.error);
});

self.addEventListener('unhandledrejection', event => {
    console.error('[SW] Unhandled promise rejection:', event.reason);
});

// ========================================
// Cleanup on unload
// ========================================
self.addEventListener('beforeunload', event => {
    console.log('[SW] Service Worker unloading');
});