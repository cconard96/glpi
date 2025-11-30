<script setup>

</script>

<template>

</template>

<style scoped>
    .articles-list {

        .line {
            color: currentcolor;
            margin: 5px;
            padding: 5px;
            font-weight: normal;
            position: relative;

            .label {
                display: block;
                font-weight: bold;
                font-size: 1.4em;
                margin: 5px 0 10px;
            }

            .content {
                display: block;
                margin-bottom: 3px;
                font-size: 1.2em;
                overflow: hidden;
                text-overflow: ellipsis;

                &.long_text {
                    max-height: 80px;
                }
            }

            .date {
                float: right;
            }

            a,
            i.fa-user {
                color: currentcolor;
            }

            i.fa-user {
                font-size: 0.8em;
            }
        }

        .main-label {
            position: absolute;
            left: 5px;
            bottom: 5px;
        }
    }
</style>
