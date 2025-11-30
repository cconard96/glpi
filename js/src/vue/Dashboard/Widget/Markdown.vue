<script setup>

</script>

<template>

</template>

<style scoped>
    .markdown {
        --mdmargin_v: 15px;

        textarea.markdown_content {
            display: none;
            width: 100%;
            height: calc(100% - var(--mdmargin_v) * 2);
            resize: none;
            padding: 5px;
            margin: var(--mdmargin_v) 0;
            background-color: rgb(255, 255, 255, 90%);
            border: 1px solid currentcolor;
            border-radius: 1px;
            outline: none;
            font-family: monospace;
        }

        ul {
            list-style-type: disc;

            li {
                margin-left: 1.5em;
            }
        }

        h1 {
            border-bottom: 2px solid currentcolor;
        }

        h2 {
            border-bottom: 1px solid currentcolor;
        }

        img {
            max-width: 100%;
            max-height: 100%;
        }
    }
</style>
