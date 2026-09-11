<script setup>
    /*!
     * GLPI - Gestionnaire Libre de Parc Informatique
     * SPDX-License-Identifier: GPL-3.0-or-later
     * SPDX-FileCopyrightText: 2015-2026 Teclib' and contributors.
     */

    import {onMounted, useTemplateRef} from "vue";

    const props = defineProps({
        article: {
            type: Object,
            required: true
        },
    });
</script>

<template>
    <div class="me-2 flex-shrink-0">
        <div>

        </div>
        <div></div>
    </div>
</template>

<style scoped>

</style>
