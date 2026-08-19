import { ref, watch } from 'vue';

const STORAGE_KEY = 'ruqya-density';

export type AppDensity = 'compact' | 'comfortable' | 'default';

const VALID_DENSITIES: AppDensity[] = ['compact', 'comfortable', 'default'];

function readStoredDensity(): AppDensity {
    if (typeof localStorage === 'undefined') {
        return 'default';
    }

    const stored = localStorage.getItem(STORAGE_KEY);
    return VALID_DENSITIES.includes(stored as AppDensity)
        ? (stored as AppDensity)
        : 'default';
}

const density = ref<AppDensity>(readStoredDensity());

watch(density, (value) => {
    localStorage.setItem(STORAGE_KEY, value);
});

export function useAppDensity() {
    function setDensity(value: AppDensity) {
        density.value = value;
    }

    return { density, setDensity };
}
