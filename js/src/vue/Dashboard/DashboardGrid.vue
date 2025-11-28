<script setup>
    import DashboardToolbar from "./DashboardToolbar.vue";
    import DashboardFilters from "./DashboardFilters.vue";
    import {onMounted, useTemplateRef} from "vue";

    const props = defineProps({
        cols: {
            type: Number,
            default: 26,
        },
        rows: {
            type: Number,
            default: 24,
        },
    });

    let grid = null;
    const dashboard_el = useTemplateRef('ref_dashboard');

    onMounted(() => {
        const elem_domRect = dashboard_el.value.getBoundingClientRect();
        const width_offset = elem_domRect.left + (window.innerWidth - elem_domRect.right) + 0.02;
        grid = GridStack.init({
            column: props.cols,
            minRow: props.rows + 1, // +1 for a hidden item at bottom (to fix height)
            float: true,
            animate: false,
            draggable: {
                cancel: 'textarea'
            },
            minWidth: 768 - width_offset,// breakpoint of one column mode (based on the dashboard container width), trying to reduce to match the `-md` breakpoint of bootstrap (this last is based on viewport width)
        }, dashboard_el.value.querySelector('.grid-stack'));
    });
</script>

<template>
    <div ref="ref_dashboard" class="dashboard">
        <span class="glpi_logo"></span>
        <DashboardToolbar context="core" dashboard_name="Test" />
        <DashboardFilters />
        <div :class="`grid-stack grid-stack-${cols} w-100`" :gs-column="cols" :gs-min-row="rows">
            <div class="grid-guide">
                <template v-for="n in cols" :key="`col-${n}`">
                    <template v-for="m in rows" :key="`col-${n}-row-${m}`">
                        <div class="cell-add" :data-x="n" :data-y="m">&nbsp</div>
                    </template>
                </template>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
