<script setup>

</script>

<template>

</template>

<style scoped>
    .summary-numbers {
        display: flex;
        flex-direction: column;

        .scrollable {
            flex-grow: 1;
            display: flex;

            .table {
                flex-grow: 1;
                display: flex;
                text-align: center;

                .line {
                    flex-grow: 1;
                    display: flex;
                    flex-direction: column-reverse;
                    justify-content: space-evenly;
                    flex-basis: 100%;
                    color: #555;
                    position: relative;
                    font-weight: normal;

                    .content {
                        display: flex;

                        .formatted-number {
                            flex-grow: 1;
                        }
                    }

                    .label {
                        font-size: 1.5em;
                    }

                    &:not(:first-child) {
                        border: 0;
                        border-left: 1px solid rgb(125, 125, 125, 20%);
                    }

                    &:hover {
                        background-color: rgb(125, 125, 125, 10%);
                        cursor: pointer;
                        border-radius: 3px;
                        border-left-color: transparent;

                        & + .line {
                            border-left-color: transparent;
                        }
                    }
                }
            }
        }
    }
</style>
