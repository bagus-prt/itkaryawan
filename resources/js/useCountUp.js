import { onBeforeUnmount, onMounted, ref } from "vue";

export function useCountUp(enabled = () => true) {
    const countProgress = ref(1);
    let frame;

    onMounted(() => {
        if (!enabled() || window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;

        const started = performance.now();
        countProgress.value = 0;

        function animate(now) {
            const progress = Math.min(1, (now - started) / 700);
            countProgress.value = 1 - (1 - progress) ** 3;
            if (progress < 1) frame = requestAnimationFrame(animate);
        }

        frame = requestAnimationFrame(animate);
    });

    onBeforeUnmount(() => {
        if (frame !== undefined) cancelAnimationFrame(frame);
    });

    return (value) => Math.round(value * countProgress.value);
}
