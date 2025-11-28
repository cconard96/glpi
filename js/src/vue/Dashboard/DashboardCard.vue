<script setup>
    import {computed} from "vue";

    const props = defineProps({
        gridstack_id: {
            type: String,
            required: true,
        },
        x: {
            type: Number,
            required: true,
            default: -1,
        },
        y: {
            type: Number,
            required: true,
            default: -1,
        },
        width: {
            type: Number,
            required: true,
            default: 2,
        },
        height: {
            type: Number,
            required: true,
            default: 2,
        },
        data: {
            type: Object,
            required: true,
        },
    });

    const color = computed(() => props.data?.color || '#ffffff');
    const fg_color = computed(() => {
        return window.tinycolor.mostReadable(color.value, window.tinycolor(color.value).monochromatic(), {
            includeFallbackColors: true,
        }).toHexString();
    })
</script>

<template>
    <div class="grid-stack-item" :gs-id="gridstack_id"
         :gs-w="width" :gs-h="height" :gs-x="x > 0 ? x : undefined" :gs-y="y > 0 ? y : undefined"
         :gs-auto-position="!(x > 0 && y > 0)">
        <span class="controls">
            <i class="refresh-item ti ti-refresh" :title="__('Refresh this card')"></i>
            <i class="edit-item ti ti-edit" :title="__('Edit this card')"></i>
            <i class="delete-item ti ti-x" :title="__('Delete this card')"></i>
        </span>
        <div class="grid-stack-item-content"></div>
    </div>
</template>

<style scoped>
    .grid-stack-item {
        color: v-bind(fg_color);
    }
</style>
