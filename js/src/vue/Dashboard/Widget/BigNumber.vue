<script setup>
    const props = defineProps({
        options: {
            type: Object,
            required: true,
        },
    });
</script>

<template>
    <Component :is="options?.url ? 'a' : 'div'" data-bs-toggle="tooltip" data-bs-placement="top" :title="options?.alt"
               :href="options.url" class="big-number">
        <span class="content" v-html="options.formatted_number"></span>
        <div class="label" v-text="options.label"></div>
        <i :class="`main-icon ${options.icon}`"></i>
    </Component>
</template>

<style scoped>
    .big-number {
        .content {
            margin: 0;
        }

        .main-icon {
            right: 5px;
            top: 5px;
        }

        :deep(.formatted-number) {
            display: flex;

            .number,
            .suffix {
                font-size: 3em;
                font-weight: normal;

                @media screen and (max-width: 700px) {
                    font-size: 2em;
                }
            }
        }

        .label {
            max-width: calc(100%);
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
</style>
