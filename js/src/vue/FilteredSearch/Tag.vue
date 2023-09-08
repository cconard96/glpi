<script setup>
    /* global escapeMarkupText */
    import SearchTokenizer from "../../../modules/SearchTokenizer/SearchTokenizer.js";
    import {computed} from "vue";

    const props = {
        tokenizer: {
            type: SearchTokenizer,
            required: true
        },
        token: {
            type: Object,
            required: true
        },
    };

    const dark_mode = document.documentElement.attr('data-glpi-theme-dark') === '1';
    const tag_display = computed(() => {
        if (!props.token.tag) {
            return '';
        }
        const exclusion = props.token.exclusion ? this.tokenizer.EXCLUSION_PREFIX : '';
        const prefix = props.token.prefix ? props.token.prefix : '';
        return `<b>${exclusion}${prefix}${props.token.tag}</b>:${escapeMarkupText(props.token.term) || ''}`;
    });
    const tag_color = computed(() => {
        let color = document.body.style.color;
        if (dark_mode) {
            color = color || '#b3b3b3';
            // remove the alpha from the hex color
            if (color.indexOf('#') === 0) {
                color = color.replace(/[^#]*#([0-9a-f]{6})([0-9a-f]{2})?/i, '#$1');
            }
        }
        return color;
    });
    const style_overrides = computed(() => {
        let style = '';
        if (dark_mode) {
            style = tag_color.value ? `style="border-color: ${tag_color.value} !important; background-color: unset !important;"` : '';
        } else {
            style = tag_color.value ? `style="background-color: ${tag_color.value} !important"` : '';
        }
        return style;
    });
</script>

<template>
    <span class="search-input-tag badge bg-secondary me-1" contenteditable="false" :data-tag="props.token.tag" :style="style_overrides">
        <span class="search-input-tag-value" contenteditable="false" v-text="tag_display"></span>
        <i class="ti ti-x cursor-pointer ms-1" :title="__('Delete')" contenteditable="false"></i>
    </span>
</template>

<style scoped>
</style>
