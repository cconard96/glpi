<script setup>

</script>

<template>

</template>

<style scoped>
    .search-table {
        .table-container {
            .tab_cadrehov {
                margin: 0;
                min-width: 100%;
                box-shadow: none;
                background-color: transparent;
                font-size: 13px;

                th {
                    position: sticky;
                    top: 0;
                    z-index: 10;
                    padding: 5px;
                    border-bottom: 0;
                    color: inherit;
                    font-size: inherit;

                    &.order_ASC::before,
                    &.order_DESC::before {
                        color: inherit;
                    }
                }

                td {
                    border-bottom: 0;
                }

                .tab_bg_1 {
                    background-color: rgb(0, 0, 0, 3%);
                }

                .tab_bg_2 {
                    background-color: transparent;
                }

                a {
                    color: inherit;
                    font-size: inherit !important;

                    &:hover {
                        text-decoration: underline;
                    }
                }
            }
        }

        .main-icon {
            font-size: 2em;
            right: 5px;
            bottom: 5px;
        }

        .main-label {
            a {
                font-size: inherit !important;
                color: inherit;
            }
        }
    }
</style>
