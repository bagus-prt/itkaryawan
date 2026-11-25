<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { IconArrowRight } from "@tabler/icons-vue";
import AppLayout from "../Layouts/AppLayout.vue";
import { useCountUp } from "@/useCountUp";

defineOptions({ layout: AppLayout });

const props = defineProps({
    dashboard: Object,
});

const page = usePage();
const number = new Intl.NumberFormat("id-ID");
const day = new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "short",
    year: "numeric",
    timeZone: "UTC",
});

const activeMonth = ref(null);
const countUp = useCountUp(() => Boolean(props.dashboard));

function animatedNumber(value) {
    return number.format(countUp(value));
}

const chart = computed(() => props.dashboard?.chart);
const chartTicks = computed(() => {
    const max = Math.max(0, ...(chart.value?.series.flatMap(({ values }) => values) ?? []));
    const rawStep = Math.max(1, max / 4);
    const magnitude = 10 ** Math.floor(Math.log10(rawStep));
    const step = magnitude * [1, 2, 5, 10].find((multiple) => multiple * magnitude >= rawStep);
    const intervals = Math.max(2, Math.ceil(max / step));

    return Array.from({ length: intervals + 1 }, (_, index) => (intervals - index) * step);
});

const chartLines = computed(() => chart.value?.series.map(({ values }) => {
    const points = values.map((value, index) => ({
        x: index * 100 / Math.max(1, values.length - 1),
        y: 100 - value * 100 / chartTicks.value[0],
    }));
    const first = points[0];
    const last = points.at(-1);
    const line = points.slice(1).reduce((path, point, index) => {
        const previous = points[index];
        const middle = (previous.x + point.x) / 2;
        return `${path} C ${middle} ${previous.y}, ${middle} ${point.y}, ${point.x} ${point.y}`;
    }, `M ${first.x} ${first.y}`);

    return { points, line, area: `${line} L ${last.x} 100 L ${first.x} 100 Z` };
}) ?? []);

watch(chart, () => { activeMonth.value = null; });

function changePeriod(event) {
    const period = event.target.value;
    router.get("/dashboard", period ? { period } : {}, {
        preserveScroll: true,
        replace: true,
    });
}

function formatDay(value) {
    return day.format(new Date(value.slice(0, 10) + "T00:00:00Z"));
}
</script>

<template>
    <Head title="Dashboard" />

    <section class="dashboard-page">
        <header class="page-heading">
            <div>
                <p v-if="dashboard" class="role-label">Dashboard {{ dashboard.role }}</p>
                <h1>Selamat datang, {{ page.props.auth?.user?.name }}</h1>
            </div>
            <div v-if="dashboard?.actions" class="quick-actions">
                <Link v-for="action in dashboard.actions" :key="action.href" :href="action.href">
                    {{ action.label }}
                </Link>
            </div>
        </header>

        <template v-if="dashboard">
            <div class="summary-grid">
                <section class="highlight" aria-labelledby="highlight-heading">
                    <div class="panel-heading">
                        <h2 id="highlight-heading">{{ dashboard.highlight.title }}</h2>
                        <label v-if="dashboard.period" class="period">
                            <span class="sr-only">Periode pemantauan surat</span>
                            <input type="month" :value="dashboard.period" @change="changePeriod" />
                        </label>
                    </div>

                    <div class="highlight-total">
                        <p class="highlight-value">
                            <span aria-hidden="true">{{ animatedNumber(dashboard.highlight.value) }}</span>
                            <span class="sr-only">{{ number.format(dashboard.highlight.value) }}</span>
                        </p>
                        <p class="highlight-caption">{{ dashboard.highlight.label }}</p>
                    </div>
                    <div class="highlight-breakdown">
                        <dl class="highlight-details">
                            <div v-for="detail in dashboard.highlight.details" :key="detail.label">
                                <dt>{{ detail.label }}</dt>
                                <dd>
                                    <span aria-hidden="true">{{ animatedNumber(detail.value) }}</span>
                                    <span class="sr-only">{{ number.format(detail.value) }}</span>
                                </dd>
                            </div>
                        </dl>
                        <p v-if="dashboard.highlight.note" class="highlight-note">{{ dashboard.highlight.note }}</p>
                    </div>
                </section>

                <div class="metrics">
                    <Link v-for="metric in dashboard.metrics" :key="metric.label" :href="metric.href" class="metric panel">
                        <div class="metric-heading">
                            <h2>{{ metric.label }}</h2>
                            <IconArrowRight :size="17" aria-hidden="true" />
                        </div>
                        <p class="metric-value">
                            <span aria-hidden="true">{{ animatedNumber(metric.value) }}</span>
                            <span class="sr-only">{{ number.format(metric.value) }}</span>
                        </p>
                        <p class="muted">{{ metric.detail }}</p>
                    </Link>
                </div>
            </div>

            <div class="detail-grid">
                <section class="chart-panel panel" aria-labelledby="chart-heading">
                    <div class="panel-heading">
                        <h2 id="chart-heading">{{ dashboard.chart.title }}</h2>
                        <ul class="legend">
                            <li v-for="(series, index) in dashboard.chart.series" :key="series.label">
                                <span class="legend-dot" :class="{ secondary: index === 1 }"></span>
                                {{ series.label }}
                            </li>
                        </ul>
                    </div>

                    <div class="chart">
                        <div class="chart-axis" aria-hidden="true">
                            <span v-for="tick in chartTicks" :key="tick">{{ number.format(tick) }}</span>
                        </div>
                        <div class="chart-body">
                            <div class="chart-plot" aria-hidden="true">
                                <div class="chart-grid">
                                    <span v-for="tick in chartTicks" :key="tick"></span>
                                </div>
                                <svg viewBox="0 0 100 100" preserveAspectRatio="none" focusable="false">
                                    <defs>
                                        <clipPath id="dashboard-chart-reveal">
                                            <rect class="chart-reveal" x="-3" y="-8" width="106" height="116" />
                                        </clipPath>
                                        <linearGradient id="dashboard-chart-fill" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#066fd1" stop-opacity=".22" />
                                            <stop offset="100%" stop-color="#066fd1" stop-opacity=".025" />
                                        </linearGradient>
                                    </defs>
                                    <g clip-path="url(#dashboard-chart-reveal)">
                                        <path :d="chartLines[0].area" fill="url(#dashboard-chart-fill)" />
                                        <path
                                            v-for="({ line }, index) in chartLines"
                                            :key="index"
                                            :d="line"
                                            class="chart-line"
                                            :class="{ secondary: index === 1 }"
                                            vector-effect="non-scaling-stroke"
                                        />
                                    </g>
                                </svg>
                                <template v-if="activeMonth !== null">
                                    <span class="chart-guide" :style="{ left: chartLines[0].points[activeMonth].x + '%' }"></span>
                                    <span
                                        v-for="({ points }, index) in chartLines"
                                        :key="index"
                                        class="chart-point"
                                        :class="{ secondary: index === 1 }"
                                        :style="{ left: points[activeMonth].x + '%', top: points[activeMonth].y + '%' }"
                                    ></span>
                                </template>
                            </div>
                            <div class="chart-months">
                                <button
                                    v-for="(month, index) in chart.months"
                                    :key="month"
                                    type="button"
                                    class="month"
                                    :aria-pressed="activeMonth === index"
                                    :title="chart.series.map((series) => series.label + ': ' + number.format(series.values[index])).join(' · ')"
                                    @click="activeMonth = activeMonth === index ? null : index"
                                >
                                    {{ month }}
                                    <span class="sr-only">
                                        {{ chart.series.map((series) => series.label + ' ' + number.format(series.values[index])).join(', ') }}
                                    </span>
                                </button>
                            </div>
                            <p class="chart-reading muted" aria-live="polite">
                                <template v-if="activeMonth !== null">
                                    <strong>{{ chart.months[activeMonth] }}</strong>
                                    <span v-for="series in chart.series" :key="series.label">
                                        {{ series.label }} {{ number.format(series.values[activeMonth]) }}
                                    </span>
                                </template>
                            </p>
                        </div>
                    </div>
                </section>

                <section class="list-panel panel" aria-labelledby="list-heading">
                    <h2 id="list-heading">{{ dashboard.panel.title }}</h2>
                    <ul v-if="dashboard.panel.rows.length" class="activity-list">
                        <li v-for="(row, index) in dashboard.panel.rows" :key="row.href ?? index" class="activity-row">
                            <div class="activity-content">
                                <Link v-if="row.href" :href="row.href" class="activity-title">{{ row.title }}</Link>
                                <p v-else class="activity-title">{{ row.title }}</p>
                                <p v-if="row.detail" class="muted">{{ row.detail }}</p>
                                <time v-if="row.date" :datetime="row.date.slice(0, 10)" class="muted">
                                    {{ formatDay(row.date) }}
                                </time>
                                <p v-if="row.waitingDays != null" class="muted">
                                    Menunggu {{ number.format(row.waitingDays) }} hari
                                </p>
                            </div>
                            <span class="badge">{{ row.badge }}</span>
                        </li>
                    </ul>
                    <p v-else class="empty muted">{{ dashboard.panel.empty }}</p>
                </section>
            </div>
        </template>

        <p v-else class="panel empty muted">Ringkasan belum tersedia untuk peran akun ini.</p>
    </section>
</template>

<style scoped>
@media (prefers-reduced-motion: no-preference) {
    .highlight,
    .metric,
    .detail-grid > .panel {
        animation: dashboard-enter 420ms cubic-bezier(.2, .7, .25, 1) both;
    }

    .metric { animation-delay: 45ms; }
    .metric:nth-child(2) { animation-delay: 85ms; }
    .metric:nth-child(3) { animation-delay: 125ms; }
    .metric:nth-child(4) { animation-delay: 165ms; }
    .detail-grid > .panel { animation-delay: 130ms; }
    .detail-grid > .panel + .panel { animation-delay: 175ms; }

    .chart-reveal {
        transform-box: fill-box;
        transform-origin: left center;
        animation: dashboard-chart-enter 680ms cubic-bezier(.2, .7, .25, 1) 200ms both;
    }
}

@keyframes dashboard-enter {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes dashboard-chart-enter {
    from { transform: scaleX(0); }
    to { transform: scaleX(1); }
}

.dashboard-page {
    display: grid;
    gap: 20px;
    color: var(--app-text);
    font-size: 14px;
}

.page-heading,
.panel-heading,
.metric-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.role-label,
.muted,
.month,
.legend {
    color: var(--app-muted);
    font-size: 12px;
}

.page-heading h1 {
    margin-top: 4px;
    font-size: clamp(23px, 3vw, 28px);
    font-weight: 600;
}

.dashboard-page h2 {
    font-size: 16px;
    font-weight: 600;
}

.quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.quick-actions a {
    padding: 8px 12px;
    border: 1px solid var(--app-border);
    border-radius: 4px;
    background: var(--app-surface);
    color: var(--app-primary);
    font-size: 13px;
    font-weight: 500;
}

.summary-grid,
.detail-grid {
    display: grid;
    gap: 16px;
}

.summary-grid {
    grid-template-columns: minmax(0, 5fr) minmax(0, 8fr);
}

.detail-grid {
    grid-template-columns: minmax(0, 3fr) minmax(0, 2fr);
}

.panel,
.highlight {
    min-width: 0;
    padding: 18px;
    border: 1px solid var(--app-border);
    border-radius: 8px;
    background: var(--app-surface);
}

.highlight {
    display: flex;
    flex-direction: column;
    padding: 0;
    overflow: hidden;
    border-color: #144d83;
    background:
        radial-gradient(ellipse at 96% 100%, rgb(66 153 225 / 42%), transparent 70%),
        linear-gradient(150deg, #102546 10%, #123f70 53%, #066fd1 100%);
    box-shadow: 0 8px 20px rgb(16 37 70 / 18%);
    color: #fff;
}

.highlight > .panel-heading {
    align-items: center;
    flex-wrap: wrap;
    padding: 18px 20px 0;
}

.highlight-total {
    display: grid;
    align-content: center;
    flex: 1;
    gap: 4px;
    padding: 14px 20px 18px;
}

.highlight-breakdown {
    padding: 13px 20px 15px;
    border-top: 1px solid rgb(255 255 255 / 20%);
    background: rgb(255 255 255 / 9%);
}

.period input {
    max-width: 141px;
    padding: 5px 8px;
    border: 1px solid rgb(255 255 255 / 30%);
    border-radius: 6px;
    background: transparent;
    color: #fff;
    color-scheme: dark;
    font: inherit;
    font-size: 12px;
}

.period input:hover {
    border-color: rgb(255 255 255 / 65%);
}

.period input:focus-visible {
    outline: 2px solid #fff;
    outline-offset: 2px;
}

.highlight-value {
    font-variant-numeric: tabular-nums;
    font-size: clamp(36px, 4vw, 48px);
    font-weight: 600;
    line-height: 1.2;
}

.highlight-caption,
.highlight-note {
    color: #e9f0f9;
    font-size: 12px;
}

.highlight-details {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px 16px;
}

.highlight-details dt {
    font-size: 12px;
}

.highlight-details dd {
    font-size: 18px;
    font-weight: 600;
}

.highlight-note {
    margin-top: 10px;
}

.metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.metric:hover,
.quick-actions a:hover {
    border-color: var(--app-primary);
}

.metric {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.metric:nth-child(4n + 2) .metric-heading svg {
    color: #045db0;
}

.metric:nth-child(4n + 3) .metric-heading svg {
    color: #4299e1;
}

.metric:nth-child(4n + 4) .metric-heading svg {
    color: #144d83;
}

.metric:last-child:nth-child(odd) {
    grid-column: 1 / -1;
}

.metric-heading svg {
    flex: none;
    color: var(--app-primary);
}

.metric-value {
    font-variant-numeric: tabular-nums;
    margin-top: 10px;
    font-size: 24px;
    font-weight: 600;
}

.legend {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 12px;
}

.legend li {
    display: flex;
    align-items: center;
    gap: 5px;
}

.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #066fd1;
}

.chart {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    margin-top: 24px;
    gap: 8px;
}

.chart-axis,
.chart-grid {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.chart-axis {
    height: 172px;
    margin-block: -8px;
    text-align: right;
    color: var(--app-muted);
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}

.chart-plot {
    position: relative;
    height: 156px;
    margin-inline: calc(100% / 12);
}

.chart-grid,
.chart-plot svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}

.chart-grid span {
    border-top: 1px solid var(--app-border);
}

.chart-plot svg {
    overflow: visible;
}

.chart-line {
    fill: none;
    stroke: #066fd1;
    stroke-width: 2px;
    stroke-linecap: round;
}

.chart-line.secondary {
    stroke: #144d83;
    stroke-dasharray: 5 4;
}

.chart-guide {
    position: absolute;
    inset-block: 0;
    border-left: 1px dashed var(--app-muted);
}

.chart-point {
    position: absolute;
    width: 9px;
    height: 9px;
    transform: translate(-50%, -50%);
    border: 2px solid #fff;
    border-radius: 50%;
    background: #066fd1;
}

.chart-point.secondary,
.legend-dot.secondary {
    background: #144d83;
}

.chart-months {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    margin-top: 6px;
}

.month {
    padding: 4px 1px;
    border-radius: 4px;
    cursor: pointer;
}

.month:hover,
.month[aria-pressed="true"] {
    background: #e9f0f9;
    color: var(--app-text);
}

.chart-reading {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 12px;
    min-height: 24px;
    margin-top: 5px;
}

.activity-list {
    margin-top: 8px;
}

.activity-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    padding-block: 11px;
}

.activity-row + .activity-row {
    border-top: 1px solid var(--app-border);
}

.activity-content {
    min-width: 0;
    overflow-wrap: anywhere;
}

.activity-content p.muted {
    display: -webkit-box;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.activity-title {
    font-weight: 500;
}

a.activity-title {
    color: var(--app-primary);
}

a.activity-title:hover {
    text-decoration: underline;
}

.badge {
    flex-shrink: 0;
    max-width: 45%;
    padding: 3px 7px;
    border-radius: 4px;
    background: #e9f0f9;
    color: #045db0;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
}

.empty {
    padding-block: 16px;
}

@media (max-width: 1050px) {
    .summary-grid,
    .detail-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 520px) {
    .page-heading,
    .chart-panel .panel-heading {
        flex-direction: column;
    }

    .metrics {
        grid-template-columns: minmax(0, 1fr);
    }

    .panel {
        padding: 14px;
    }

    .highlight > .panel-heading,
    .highlight-total,
    .highlight-breakdown {
        padding-inline: 14px;
    }

    .month {
        font-size: 10px;
    }
}
</style>
