<script setup>
    import DashboardToolbar from "./DashboardToolbar.vue";
    import DashboardFilters from "./DashboardFilters.vue";
    import {onMounted, useTemplateRef, ref, provide} from "vue";
    import Card from "./Card.vue";

    const props = defineProps({
        current: {
            type: String,
            required: true,
        },
        embed: {
            type: Boolean,
            default: false,
        },
        context: {
            type: String,
            default: 'core',
        },
        items: {
            type: Array,
            default: () => [],
        },
        cols: {
            type: Number,
            default: 26,
        },
        rows: {
            type: Number,
            default: 24,
        },
        cell_margin: {
            type: Number,
            default: 3,
        },
        cache_key: {
            type: String,
            required: true,
        },
    });

    let grid = null;
    const dashboard_el = useTemplateRef('ref_dashboard');
    const cards = ref(props.items);
    provide('dashboard', {
        'context': props.context,
        'current': props.current,
        'embed': props.embed,
        'cache_key': props.cache_key,
        'grid': grid,
    });

    onMounted(() => {
        const elem_domRect = dashboard_el.value.getBoundingClientRect();
        const width_offset = elem_domRect.left + (window.innerWidth - elem_domRect.right) + 0.02;
        grid = GridStack.init({
            column: props.cols,
            minRow: props.rows + 1, // +1 for a hidden item at bottom (to fix height)
            margin: props.cell_margin,
            float: true,
            animate: false,
            draggable: {
                cancel: 'textarea'
            },
            minWidth: 768 - width_offset,// breakpoint of one column mode (based on the dashboard container width), trying to reduce to match the `-md` breakpoint of bootstrap (this last is based on viewport width)
        }, dashboard_el.value.querySelector('.grid-stack'));
        grid.setStatic(true);
    });
</script>

<template>
    <div ref="ref_dashboard" class="dashboard">
        <span class="glpi_logo"></span>
        <DashboardToolbar context="core" dashboard_name="Test" />
        <DashboardFilters />
        <div :class="`grid-stack grid-stack-${cols} w-100`" :gs-column="cols" :gs-min-row="rows">
            <Card v-for="card in cards"
                           :key="card.card_id" :card_id="card.card_id" :gridstack_id="card.gridstack_id"
                           :x="card.x" :y="card.y" :width="card.width" :height="card.height"
                           :card_options="card.card_options" />
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

<style scoped lang="scss">
    .dashboard {
        position: relative;
        --glpi-dashboard-soft-border: rgb(color-mix(in srgb, var(--tblr-light), var(--tblr-dark) 64%), 0.24);
        padding-top: 40px;

        @mixin custom-scroll-bar() {
            // firefox
            scrollbar-width: none;

            // chrome
            &::-webkit-scrollbar {
                height: 6px;
                width: 4px;
            }

            &::-webkit-scrollbar-thumb,
            &::-webkit-scrollbar-track,
            &::-webkit-scrollbar-corner {
                background-color: transparent;
            }

            &:hover {
                // firefox
                scrollbar-width: thin;

                // chrome
                &::-webkit-scrollbar-thumb {
                    background-color: rgb(0, 0, 0, 30%);
                }

                &::-webkit-scrollbar-track {
                    background-color: rgb(0, 0, 0, 20%);
                }
            }
        }

        // child component usage of the scroll bar mixin
        .multiple-numbers .scrollable, .articles-list .scrollable {
            overflow: auto;
            max-height: calc(100% - 35px);
            @include custom-scroll-bar();
        }

        .markdown {
            overflow-y: auto;
            @include custom-scroll-bar;
        }

        .search-table .table-container {
            overflow: auto;
            max-height: calc(100% - 40px);
            margin-top: 10px;
            @include custom-scroll-bar;
        }
        // end scroll bar mixin usage

        &.mini {
            padding-top: 0;
            margin: 0 auto;
            margin-bottom: -45px !important;
            z-index: 1;
            box-sizing: content-box;

            & + .search_page {
                z-index: 2;
            }

            .card {
                padding: 0 3px;
            }

            .grid-guide {
                top: 0;
            }

            .main-icon {
                font-size: 1.5em;
                right: 3px;
                bottom: 3px;
            }

            .grid-stack .grid-stack-item.lock-bottom {
                min-height: 0;
                height: 0;
            }
        }

        &.fullscreen,
        &.embed {
            background: white;
            padding: 100px;

            .glpi_logo {
                background: var(--glpi-logo-dark) no-repeat;
                width: 100px;
                height: 55px;
                position: absolute;
                top: 30px;
                left: 100px;
            }

            .toolbar {
                top: 50px;
                right: 50px;

                &.left-toolbar {
                    top: 40px;
                    left: 250px;
                }

                .fs-toggle {
                    display: none;
                }
            }
        }

        .grid-guide {
            display: none;
            position: absolute;
            top: 0;
            bottom: var(--gs-cell-height);
            width: calc(100% + 1px);
            border: 1px solid var(--tblr-body-bg);
            border-width: 0 1px 1px 0;
            background-image:
                linear-gradient(to right, var(--tblr-body-bg) 1px, transparent 1px),
                linear-gradient(to bottom, var(--tblr-body-bg) 1px, transparent 1px);
            background-size: var(--gs-cell-height) var(--gs-cell-height);

            .cell-add {
                opacity: 0;
                z-index: 2;
                position: relative;
                cursor: pointer;
                border: 2px dashed #777;
                width: var(--gs-cell-height);
                height: var(--gs-cell-height);

                &:hover {
                    opacity: 1;
                }

                &::after {
                    content: "\2b";
                    left: 40%;
                    top: 40%;
                    color: grey;
                    font: var(--fa-font-solid);
                    font-size: 1em;
                    position: absolute;
                }
            }
        }
    }
</style>
