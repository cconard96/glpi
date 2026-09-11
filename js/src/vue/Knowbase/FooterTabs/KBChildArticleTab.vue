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
    <ul class="kb-child-articles list-unstyled mb-0">
        <li v-for="childArticle in article.child_articles" :key="childArticle.id" class="kb-child-article">
            <a :href="childArticle.url" class="d-flex align-items-center gap-2 text-body text-hover-link text-decoration-none">
                <span class="kb-child-article-icon d-inline-flex align-items-center justify-content-center" aria-hidden="true">
                    <div v-if="article.illustration">
                        <!-- TODO illustration icon -->
                    </div>
                    <i v-else class="ti ti-lifebuoy text-secondary" aria-hidden="true"></i>
                </span>
            </a>
        </li>
    </ul>
</template>

<style scoped>
    .kb-child-articles {
        li {
            padding: 0.25rem 0;
            a:hover .text-truncate {
                text-decoration: underline;
            }
        }
        .kb-child-article-icon {
            flex: 0 0 20px;
            height: 20px;
        }
    }
</style>
