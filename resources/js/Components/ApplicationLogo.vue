<script setup> import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

// Mengambil path logo dari shared data settings
const logoPath = computed(() => page.props.settings?.agency_logo);
const agencyName = computed(() => page.props.settings?.agency_name || 'Logo');
</script>

<template>
    <div class="h-10 w-10 flex items-center justify-center overflow-hidden rounded-full border-2 border-indigo-100 bg-white shadow-sm aspect-square">
        
        <img 
            v-if="logoPath" 
            :src="'/storage/' + logoPath" 
            :alt="agencyName"class="h-full w-full object-cover" 
        />
        
        <div 
            v-else 
            class="h-full w-full bg-gradient-to-br from-indigo-600 to-indigo-700 flex items-center justify-center text-white font-black text-lg uppercase"
        >
            {{ agencyName.charAt(0) }}
        </div>
    </div>
</template>