<script>
// Singleton Global Request Queue (Shared across all SecureThumbnail instances)
const MAX_CONCURRENT = 3;
let activeCount = 0;
const requestQueue = [];
const cacheLoadedUrls = new Set();

function processNext() {
    while (activeCount < MAX_CONCURRENT && requestQueue.length > 0) {
        const task = requestQueue.shift();
        activeCount++;
        task.run().finally(() => {
            activeCount--;
            processNext();
        });
    }
}

function enqueueThumbnail(url, onReady, onError) {
    if (!url) return () => {};
    if (cacheLoadedUrls.has(url)) {
        onReady(url);
        return () => {};
    }

    let isCanceled = false;
    let retryTimeout = null;

    const task = {
        url,
        run: () => {
            return new Promise((resolve) => {
                if (isCanceled) {
                    resolve();
                    return;
                }

                const img = new Image();

                const cleanup = () => {
                    img.onload = null;
                    img.onerror = null;
                };

                img.onload = () => {
                    cleanup();
                    // Jika server mengembalikan 1x1 placeholder (status 202 - generating)
                    if (img.naturalWidth === 1 && img.naturalHeight === 1) {
                        retryTimeout = setTimeout(() => {
                            if (!isCanceled) {
                                enqueueThumbnail(url, onReady, onError);
                            }
                        }, 1500);
                        resolve();
                        return;
                    }

                    cacheLoadedUrls.add(url);
                    if (!isCanceled) {
                        onReady(url);
                    }
                    resolve();
                };

                img.onerror = () => {
                    cleanup();
                    if (!isCanceled) {
                        onError();
                    }
                    resolve();
                };

                img.src = url;
            });
        }
    };

    requestQueue.push(task);
    processNext();

    // Cancel callback jika komponen unmount atau keluar viewport
    return () => {
        isCanceled = true;
        if (retryTimeout) clearTimeout(retryTimeout);
        const idx = requestQueue.indexOf(task);
        if (idx !== -1) {
            requestQueue.splice(idx, 1);
        }
    };
}
</script>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    src: {
        type: String,
        required: true
    },
    alt: {
        type: String,
        default: ''
    },
    isArw: {
        type: Boolean,
        default: false
    },
    aspectClass: {
        type: String,
        default: 'aspect-4/3 sm:aspect-square'
    },
    imgClass: {
        type: String,
        default: 'w-full h-full object-cover select-none pointer-events-none'
    },
    showLock: {
        type: Boolean,
        default: true
    },
    containerClass: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['click', 'dblclick', 'error']);

const containerRef = ref(null);
const isVisible = ref(false);
const isLoaded = ref(false);
const hasError = ref(false);
const displayUrl = ref('');
let cancelQueue = null;
let observer = null;

const startLoading = () => {
    if (!props.src || isLoaded.value) return;
    
    cancelQueue = enqueueThumbnail(
        props.src,
        (loadedUrl) => {
            displayUrl.value = loadedUrl;
            isLoaded.value = true;
        },
        () => {
            hasError.value = true;
            emit('error');
        }
    );
};

onMounted(() => {
    if (typeof window === 'undefined' || !('IntersectionObserver' in window)) {
        startLoading();
        return;
    }

    observer = new IntersectionObserver((entries) => {
        const entry = entries[0];
        if (entry && entry.isIntersecting) {
            isVisible.value = true;
            startLoading();
            if (observer && containerRef.value) {
                observer.unobserve(containerRef.value);
            }
        }
    }, {
        rootMargin: '250px 0px 250px 0px', // preload 250px sebelum masuk viewport
        threshold: 0.01
    });

    if (containerRef.value) {
        observer.observe(containerRef.value);
    }
});

onUnmounted(() => {
    if (observer) {
        observer.disconnect();
        observer = null;
    }
    if (cancelQueue) {
        cancelQueue();
    }
});

watch(() => props.src, (newSrc, oldSrc) => {
    if (newSrc !== oldSrc) {
        isLoaded.value = false;
        hasError.value = false;
        displayUrl.value = '';
        if (cancelQueue) cancelQueue();
        if (isVisible.value) {
            startLoading();
        }
    }
});
</script>

<template>
    <div 
        ref="containerRef"
        @click="emit('click', $event)"
        @dblclick="emit('dblclick', $event)"
        :class="[
            'relative w-full rounded-xl overflow-hidden flex items-center justify-center border border-slate-200/80 group-hover:border-indigo-400 transition shadow-2xs select-none',
            aspectClass,
            containerClass
        ]"
    >
        <!-- 1. Skeleton Loading Shimmer (Sebelum gambar termuat) -->
        <div 
            v-if="!isLoaded && !hasError"
            class="absolute inset-0 bg-slate-100 flex items-center justify-center overflow-hidden"
        >
            <div class="w-full h-full bg-gradient-to-r from-slate-100 via-slate-200/70 to-slate-100 animate-pulse flex items-center justify-center">
                <svg class="w-7 h-7 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
        </div>

        <!-- 2. Gambar Thumbnail Asli (Fade-In Halus) -->
        <img 
            v-if="isLoaded && !hasError"
            :src="displayUrl" 
            :alt="alt"
            @contextmenu.prevent=""
            draggable="false"
            :class="[
                imgClass,
                'transition-all duration-300 opacity-100 group-hover:scale-105'
            ]"
        />

        <!-- 3. Fallback jika Gagal Muat (Vector Fallback) -->
        <div 
            v-else-if="hasError" 
            class="absolute inset-0 bg-slate-50 flex flex-col items-center justify-center p-2 text-center"
        >
            <svg class="w-7 h-7 text-amber-500/80 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter truncate max-w-full">
                Pratinjau
            </span>
        </div>

        <!-- Badge RAW jika ARW -->
        <span 
            v-if="isArw" 
            class="absolute top-1.5 left-1.5 px-1.5 py-0.5 text-[8px] sm:text-[9px] bg-amber-500 text-white rounded font-black uppercase shadow-xs z-10 pointer-events-none"
        >
            RAW
        </span>

        <!-- Ikon Gembok Minimalis -->
        <span 
            v-if="showLock" 
            class="absolute top-1.5 right-1.5 p-1 bg-black/40 backdrop-blur-xs rounded-full text-white/90 z-10 pointer-events-none"
        >
            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
            </svg>
        </span>
    </div>
</template>
