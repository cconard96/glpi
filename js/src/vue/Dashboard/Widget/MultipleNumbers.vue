<script setup>

</script>

<template>

</template>

<style scoped>
    .multiple-numbers {
        --number-size: 1.9em;

        .table {
            display: table;
            width: 100%;

            .line {
                display: table-row;
                color: currentcolor;

                * {
                    display: table-cell;
                }

                .content {
                    font-size: var(--number-size);
                    min-width: 40px;
                    padding: 10px;
                    text-align: center;
                    white-space: nowrap;
                    width: 1%;
                }

                .label,
                .icon {
                    position: inherit;
                    font-size: inherit;
                    padding: 10px 0 10px 10px;
                    line-height: var(--number-size);
                }

                .icon {
                    color: rgb(0, 0, 0, 50%);
                }
            }
        }

        .main-label {
            position: absolute;
            left: 5px;
            bottom: 5px;
        }
    }
</style>
