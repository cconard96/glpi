<script setup>
    import {computed} from "vue";
    import DashboardCardContent from "./DashboardCardContent.vue";

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
        card_id: {
            type: String,
            required: true,
        },
        card_options: {
            type: Object,
            required: true,
        },
    });
</script>

<template>
    <div class="grid-stack-item" :gs-id="gridstack_id"
         :gs-w="width" :gs-h="height" :gs-x="x >= 0 ? x : undefined" :gs-y="y >= 0 ? y : undefined"
         :gs-auto-position="!(x >= 0 && y >= 0)">
        <span class="controls">
            <i class="refresh-item ti ti-refresh" :title="__('Refresh this card')"></i>
            <i class="edit-item ti ti-edit" :title="__('Edit this card')"></i>
            <i class="delete-item ti ti-x" :title="__('Delete this card')"></i>
        </span>
        <div class="grid-stack-item-content">
            <Suspense>
                <template #default>
                    <DashboardCardContent :gridstack_id="gridstack_id" :card_id="card_id" :card_options="card_options" />
                </template>
                <template #fallback>
                    <div class="loading-card">
                        <span class="spinner-border spinner-border" role="status" aria-hidden="true"></span>
                    </div>
                </template>
            </Suspense>
        </div>
    </div>
</template>

<style scoped>
    .grid-stack-item {
        &:hover {
            cursor: default;
        }

        &.lock-bottom {
            display: none;
        }

        & > .ui-resizable-se {
            bottom: 14px;
            right: 3px;
        }

        & > .controls {
            display: none;
            position: absolute;
            top: 7px;
            right: 12px;
            z-index: 11;
            cursor: pointer;

            i.fas,
            i.fa-solid,
            i.far,
            i.fa-regular,
            i.ti {
                opacity: 0.6;

                &:hover {
                    opacity: 1;
                }
            }
        }

        .empty-card {
            height: 100%;
            border-radius: 3px;
            border: 1px solid transparent;

            .fas,
            .fa-solid,
            .far,
            .fa-regular,
            .ti {
                position: absolute;
                top: calc(50% - 16px);
                left: calc(50% - 16px);
                font-size: 2em;
            }
        }

        .card-error {
            border-color: rgb(100, 0, 0, 30%);
            background: rgb(255, 0, 0, 10%);
            color: rgb(100, 0, 0, 50%);
        }

        .card-warning {
            border-color: rgb(105, 100, 32, 30%);
            background: rgb(255, 238, 0, 17.8%);
            color: rgb(105, 100, 32, 30%);
        }

        .no-data {
            display: block;
            position: relative;

            div {
                position: absolute;
                top: 50%;
                text-align: center;
                width: 100%;
            }
        }

        .ui-resizable-se {
            background: none;
            text-indent: unset;

            &::before {
                content: "\f338";
                font: var(--fa-font-solid);
                font-size: 13px;
                position: absolute;
                bottom: -5px;
                right: 1px;
                width: 20px;
                height: 20px;
                opacity: 0.6;
            }

            &:hover {
                &::before {
                    opacity: 1;
                }
            }
        }

        .grid-stack-item-content {
            cursor: default;
            touch-action: initial;

            .debug-card {
                z-index: 10;
                position: absolute;
                color: rgb(255, 0, 0, 50%);
                font-size: 10px;
                bottom: 5px;
                left: 5px;
            }
        }
    }

    .grid-stack-item-content {
        container-type: inline-size;
    }
</style>
