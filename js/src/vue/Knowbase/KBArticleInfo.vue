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
        canComment: {
            type: Boolean,
            required: true
        }
    });
</script>

<template>
    <div v-if="article.id" class="text-muted d-flex align-items-center gap-3">
        <div class="d-flex align-items-center" data-testid="last-update">
            <i class="ti ti-clock me-1 fs-3" aria-hidden="true"></i>
            <span>
                <span v-text="__('Updated:')"></span>&nbsp;
                <time :title="article.last_update_date" data-bs-toggle="tooltip" data-bs-placement="bottom" v-text="article.last_update_relative_date"></time>
                &nbsp;<span v-text="__('by')"></span>
            </span>
            <address class="mb-0">
                <a
                    v-if="article.last_update_author_link && article.last_update_can_view_author"
                    rel="author" :href="article.last_update_author_link" class="badge bg-azure-lt ms-1"
                    v-text="article.last_update_author_name"></a>
                <span v-else class="badge ms-1" v-text="article.last_update_author_name"></span>
            </address>
        </div>

        <div class="d-flex align-items-center">
            <i class="ti ti-eye me-1 fs-3" aria-hidden="true"></i>
            <span v-text="article.views === 0 ? __('No views') : _n('%s view', '%s views', article.views).replace('%s', article.views)"></span>
        </div>

        <div class="d-flex align-items-center">
            <i class="ti ti-language me-1 fs-3" aria-hidden="true"></i>
            <a href="#" class="text-muted pointer-events-none"
               v-text="_n('%s language', '%s languages', article.translations_count).replace('%s', article.translations_count)"></a>
        </div>

        <div v-if="article.documents_count" class="d-flex align-items-center">
            <i class="ti ti-paperclip me-1 fs-3" aria-hidden="true"></i>
            <a href="#kb-documents" class="text-muted"
               v-text="_n('%s document', '%s documents', article.documents_count).replace('%s', article.documents_count)"></a>
        </div>

        <div v-if="article.related_items_count" class="d-flex align-items-center">
            <i class="ti ti-link me-1 fs-3" aria-hidden="true"></i>
            <a href="#kb-documents" class="text-muted"
               v-text="_n('%s related item', '%s related items', article.related_items_count).replace('%s', article.related_items_count)"></a>
        </div>

        <div v-if="canComment && article.comments_count" class="d-flex align-items-center">
            <i class="ti ti-message-circle me-1 fs-3" aria-hidden="true"></i>
            <button type="button" class="btn btn-link p-0 text-muted text-decoration-none border-0"
                    v-text="_n('%s comment', '%s comments', article.comments_count).replace('%s', article.comments_count)">
            </button>
        </div>

        <div
            v-if="editModeEnabled && (article.begin_date || article.end_date)"
            class="d-flex align-items-center"
        >
            <i class="ti ti-calendar-clock me-1 fs-3" aria-hidden="true"></i>
            <button type="button" class="btn btn-link p-0 text-muted text-decoration-none border-0" v-text="__('Scheduled')"></button>
        </div>
    </div>
</template>

<style scoped>

</style>
