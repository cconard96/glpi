<script setup>
    /*!
     * GLPI - Gestionnaire Libre de Parc Informatique
     * SPDX-License-Identifier: GPL-3.0-or-later
     * SPDX-FileCopyrightText: 2015-2026 Teclib' and contributors.
     */
    const props = defineProps({
        article: {
            type: Object,
            required: true
        },
        editModeEnabled: {
            type: Boolean,
            required: true
        },
        commentAnchors: {
            type: Array,
            default: () => []
        },
        commentAnchorMaxLength: {
            type: Number,
            default: 1000
        }
    });
</script>

<template>
    <div class="rich_text_container article-container mt-4" v-dompurify-html="article.answer">

    </div>
</template>

<style scoped>

</style>
