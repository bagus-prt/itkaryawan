<script setup>
import { onMounted, ref } from "vue";

const props = defineProps({
    label: {
        type: String,
        default: "Tanda tangan",
    },
    error: {
        type: String,
        default: "",
    },
    canvasWidth: {
        type: Number,
        default: 720,
    },
    canvasHeight: {
        type: Number,
        default: 180,
    },
    maxDisplayWidth: {
        type: Number,
        default: 720,
    },
});

const emit = defineEmits(["update:modelValue"]);
const canvas = ref(null);
let drawing = false;

function context() {
    return canvas.value.getContext("2d");
}

function point(event) {
    const bounds = canvas.value.getBoundingClientRect();

    return {
        x: ((event.clientX - bounds.left) / bounds.width) * canvas.value.width,
        y: ((event.clientY - bounds.top) / bounds.height) * canvas.value.height,
    };
}

function startDrawing(event) {
    drawing = true;
    canvas.value.setPointerCapture(event.pointerId);
    const position = point(event);
    const drawingContext = context();

    drawingContext.beginPath();
    drawingContext.moveTo(position.x, position.y);
}

function draw(event) {
    if (!drawing) {
        return;
    }

    const position = point(event);
    const drawingContext = context();

    drawingContext.lineWidth = Math.max(3, props.canvasHeight / 60);
    drawingContext.lineCap = "round";
    drawingContext.lineJoin = "round";
    drawingContext.strokeStyle = "#182433";
    drawingContext.lineTo(position.x, position.y);
    drawingContext.stroke();
    emit("update:modelValue", canvas.value.toDataURL("image/png"));
}

function stopDrawing() {
    drawing = false;
}

function clear() {
    context().clearRect(0, 0, canvas.value.width, canvas.value.height);
    emit("update:modelValue", "");
}

onMounted(() => {
    context().lineWidth = 3;
});
</script>

<template>
    <div class="signature-field">
        <div class="signature-heading">
            <label>{{ props.label }}</label>
            <button type="button" class="signature-clear" @click="clear">
                Hapus tanda tangan
            </button>
        </div>
        <canvas
            ref="canvas"
            class="signature-canvas"
            :width="props.canvasWidth"
            :height="props.canvasHeight"
            :style="{ maxWidth: `${props.maxDisplayWidth}px` }"
            aria-label="Area untuk menggambar tanda tangan"
            @pointerdown="startDrawing"
            @pointermove="draw"
            @pointerup="stopDrawing"
            @pointercancel="stopDrawing"
            @pointerleave="stopDrawing"
        />
        <p v-if="props.error" class="signature-error">{{ props.error }}</p>
        <p v-else class="signature-hint">Gambar tanda tangan di dalam kotak.</p>
    </div>
</template>

<style scoped>
.signature-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}

.signature-heading label {
    color: #263445;
    font-weight: 600;
}

.signature-clear {
    padding: 4px 0;
    color: #066fd1;
    background: transparent;
    border: 0;
    cursor: pointer;
    font: inherit;
    font-size: 12px;
}

.signature-canvas {
    display: block;
    width: 100%;
    height: auto;
    background: #fff;
    border: 1px solid #cbd4de;
    border-radius: 6px;
    cursor: crosshair;
    touch-action: none;
}

.signature-hint,
.signature-error {
    margin: 6px 0 0;
    color: #718096;
    font-size: 12px;
}

.signature-error {
    color: #be123c;
}
</style>
