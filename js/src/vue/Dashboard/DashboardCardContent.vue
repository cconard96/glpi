<script setup>
    import {ref, inject, onMounted, computed} from "vue";
    import BigNumber from "./Widget/BigNumber.vue";

    const props = defineProps({
        gridstack_id: {
            type: String,
            required: true,
        },
        card_id: {
            type: String,
            required: true,
        },
        card_options: {
            type: Object,
            required: true,
        },
    });

    const dashboard = inject('dashboard');
    const html = ref('');
    const data = ref(null);

    onMounted(async () => {
        await $.ajax({
            url: `${CFG_GLPI.root_doc}/ajax/dashboard.php`,
            method: 'GET',
            data: {
                action: 'get_card',
                dashboard: dashboard.current,
                force: 0,
                card_id: props.card_id,
                args: {
                    ...props.card_options,
                    gridstack_id: props.gridstack_id,
                },
                d_cache_key: dashboard.cache_key,
                c_cache_key: props.card_options.cache_key || '',
            }
        }).then(r => {
            data.value = r;
        });
    });

    const bg_color = ref(props.card_options?.color || '#ffffff');
    const fg_color = computed(() => {
        const choices = [
            window.tinycolor(bg_color.value).lighten(50),
            window.tinycolor(bg_color.value).lighten(75),
            window.tinycolor(bg_color.value).darken(50),
            window.tinycolor(bg_color.value).darken(75),
        ];
        return window.tinycolor.mostReadable(bg_color.value, choices, {
            includeFallbackColors: true,
        }).toHexString();
    });
</script>

<template>
    <div class="h-100 w-100 widget-container">
        <BigNumber v-if="data && data.widget === 'bigNumber'" :options="data.data" />
    </div>
</template>

<style scoped>
    :deep(.big-number) .formatted-number, :deep(.big-number) .formatted-number, :deep(.big-number) .line .label, :deep(.big-number) .label {
        transition: font-size 0.3s ease;
        font-size: 12px;
    }

    @container (min-width: 130px) {
        :deep(.big-number) .formatted-number, :deep(.big-number) .formatted-number, :deep(.big-number) .line .label, :deep(.big-number) .label {
            font-size: 14px;
        }
    }

    @container (min-width: 180px) {
        :deep(.big-number) .formatted-number, :deep(.big-number) .formatted-number, :deep(.big-number) .line .label, :deep(.big-number) .label {
            font-size: 18px;
        }
    }

    :deep(.main-icon) {
        font-size: 1.5em;
        right: 3px;
        bottom: 3px;
    }

    .widget-container {
        background-color: v-bind(bg_color);
        color: v-bind(fg_color);
        text-align: left;
        padding: 5px;
        height: 100%;
        width: 100%;
        display: block;
        border: 2px solid transparent;
        border-radius: 3px !important;
        position: relative;

        img {
            max-width: 100%;
        }

        a {
            font-weight: normal;
            color: inherit;
        }

        :deep(.main-icon) {
            font-size: 3em;
            position: absolute;
            right: 5px;
            bottom: 5px;

            @media screen and (min-width: 700px) and (max-width: 1400px) {
                font-size: 2em;
            }
        }

        .main-label {
            margin: 5px;
            font-size: 1.5em;
            font-weight: bold;
            display: block;
            max-width: calc(100% - 2.5em);

            @media screen and (min-width: 700px) and (max-width: 1400px) {
                font-size: 1.1em;
            }

            i {
                color: currentcolor;
            }
        }
    }
</style>
