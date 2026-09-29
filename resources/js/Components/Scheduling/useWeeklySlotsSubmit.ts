import { flattenGrid, type WeeklyGrid } from '@/utils/weeklySchedule';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * PUTs a flattened weekly grid and maps `slots.N.*` validation errors
 * back onto the originating day/range (`${day}-${index}`); any other
 * error ends up in `generalErrors`.
 */
export function useWeeklySlotsSubmit() {
    const rowErrors = ref<Record<string, string>>({});
    const generalErrors = ref<string[]>([]);
    const processing = ref(false);

    function submit(url: string, grid: WeeklyGrid, extra: Record<string, unknown>, onSuccess: () => void) {
        const { slots, origin } = flattenGrid(grid);
        rowErrors.value = {};
        generalErrors.value = [];
        processing.value = true;

        router.put(
            url,
            { ...extra, slots } as never,
            {
                preserveScroll: true,
                onSuccess,
                onError: (errors) => {
                    for (const [key, message] of Object.entries(errors)) {
                        const match = key.match(/^slots\.(\d+)\./);
                        const source = match ? origin[Number(match[1])] : undefined;
                        if (source) {
                            rowErrors.value[`${source.day}-${source.index}`] ??= message;
                        } else {
                            generalErrors.value.push(message);
                        }
                    }
                },
                onFinish: () => {
                    processing.value = false;
                },
            },
        );
    }

    return { rowErrors, generalErrors, processing, submit };
}
