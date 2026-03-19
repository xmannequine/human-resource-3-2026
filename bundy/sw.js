// Service Worker for caching face-api models
const CACHE_NAME = 'face-models-v1';
const URLS_TO_CACHE = [
    './models/tiny_face_detector_model-weights_manifest.json',
    './models/tiny_face_detector_model-shard1',
    './models/face_landmark_68_model-weights_manifest.json',
    './models/face_landmark_68_model-shard1',
    './models/face_recognition_model-weights_manifest.json',
    './models/face_recognition_model-shard1',
    './models/face_recognition_model-shard2'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(URLS_TO_CACHE);
        })
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request).then((response) => {
            return response || fetch(event.request);
        })
    );
});
