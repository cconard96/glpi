<script setup>

</script>

<template>

</template>

<style scoped lang="scss">
    .filters_toolbar {
        text-align: left;
        height: 62px;
        margin: 0 5px 5px 2px;
        padding-top: 0.5em;

        @media screen and (max-width: 700px) {
            height: inherit;
        }

        .filters {
            display: inline-flex;
            flex-wrap: wrap;
            align-items: stretch;

            fieldset.filter {
                --tblr-box-shadow-input: none;

                border: 2px solid var(--tblr-border-color);
                border-radius: 4px;
                padding: 6px 12px 5px;
                text-align: center;
                margin: 6px 2px 0;
                font-size: 0.8rem;

                legend {
                    display: none;
                    color: #74838f;
                    font-weight: bold;
                    font-size: 12px;
                    float: none;
                    margin-bottom: 0;
                    padding: 0 2px;
                    width: auto;
                }

                input {
                    border: none !important;
                    cursor: pointer;
                    background-color: transparent;
                    padding: 0;
                    display: inline-block;
                    width: auto;
                    font-weight: bold;
                    font-size: 12px;
                }

                .flatpickr {
                    position: relative;

                    button[data-clear] {
                        display: none;
                        border: none;
                    }

                    &::after {
                        font-weight: 400;
                        content: "\f133";
                        right: 5px;
                    }

                    .input-group-text {
                        background-color: transparent;
                        border: none;
                    }
                }

                .select2 {
                    & ~ a,
                    & ~ span { // remove icons next select2
                        display: none;
                    }

                    // remove dropdown buttons
                    & ~ .btn {
                        display: none;
                    }

                    .select2-selection.select2-selection--single {
                        border: none;
                        background-color: transparent;
                        height: 24px;
                    }

                    .select2-container {
                        margin-right: 0;
                    }

                    .select2-selection__rendered {
                        color: #74838f;
                        font-weight: bold;
                        font-size: 12px;
                        line-height: 18px;
                    }

                    .select2-selection__arrow {
                        display: none;
                    }

                    .select2-selection__clear {
                        color: transparent;
                        position: relative;

                        &::after {
                            position: absolute;
                            color: #509ee3;
                            font: var(--fa-font-solid);
                            font-size: 11px;
                            content: "\f057";
                            opacity: 0.7;
                            left: 7px;
                        }

                        &:hover::after {
                            opacity: 1;
                        }
                    }

                    &::after {
                        font-weight: 900;
                        content: "\f02b";
                    }
                }

                .flatpickr,
                .select2 {
                    &::placeholder,
                    .select2-selection__placeholder {
                        color: #74838f;
                        opacity: 0.7;
                    }

                    &::after {
                        font: var(--fa-font-solid);
                        font-size: 14px;
                        color: #74838f;
                        opacity: 0.7;
                        pointer-events: none;
                        position: absolute;
                        right: 0;
                        top: 1px;
                    }
                }

                .delete-filter {
                    display: none;
                    cursor: pointer;
                }

                &.filled {
                    border-color: #509ee3;
                    margin-top: 0;
                    padding-top: 0.25em;

                    legend {
                        display: inline-block;
                    }

                    input,
                    .select2-selection__rendered {
                        color: #509ee3 !important;
                    }

                    .flatpickr,
                    .select2 {
                        &::after {
                            display: none;
                        }

                        button[data-clear] {
                            display: inline-block;
                            color: #509ee3;
                        }
                    }
                }
            }
        }

        .filters-control {
            border: 2px dashed transparent;
            border-radius: 4px;
            display: inline-block;
            vertical-align: top;
            margin-top: 12px;

            .add-filter {
                padding: 8px 10px 9px 12px;
                display: none;
                cursor: pointer;

                .no-filter,
                .add-filter-lbl {
                    padding-left: 0.2em;
                    font-style: italic;
                    color: #74838f;
                    font-family: Arial, Helvetica, sans-serif;
                    font-weight: bold;
                    font-size: 12px;
                }
            }
        }
    }
</style>
