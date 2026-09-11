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
        canEdit: {
            type: Boolean,
            required: true
        }
    });
</script>

<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <!-- TODO -->
        <span v-for="related_item in article.related_items" :key="related_item.id" class="kb-item-badge badge d-flex align-items-center gap-2">
            <a :href="related_item.link_url"
               class="d-flex align-items-center gap-2 text-decoration-none"
               :title="`${related_item.type_name}: ${related_item.name}`"
            >
                <i :class="`${related_item.icon_class} ${related_item.color_class}`" aria-hidden="true"></i>
                <span>{{ related_item.name }}</span>
            </a>
            <button v-if="canEdit" type="button" class="kb-unlink-item btn-close"
                    :title="__('Unlink item')" :aria-label="__('Unlink item')"></button>
        </span>
    </div>

    <button v-if="article.can_link_items" type="button" class="btn btn-sm btn-outline-secondary mt-2">
        <i class="ti ti-link me-1" aria-hidden="true"></i>{{ __('Link to another item') }}
    </button>
</template>

<style scoped>
    .kb-item-badge {
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
        font-weight: 400;
        background-color: var(--tblr-bg-surface-secondary);
        color: var(--tblr-body-color);
        border: 1px solid var(--tblr-border-color);
        transition: all 0.2s ease;

        a {
            color: inherit;
        }

        .kb-unlink-item {
            &:hover {
                color: var(--tblr-danger) !important;
            }
        }

        .kb-item-badge:focus-within {
            .kb-unlink-item {
                opacity: 1;
            }
        }

        .kb-item-badge:hover {
            border-color: var(--tblr-secondary);
        }
    }
</style>
