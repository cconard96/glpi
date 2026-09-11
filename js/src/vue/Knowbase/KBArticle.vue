<script setup>
    /*!
     * GLPI - Gestionnaire Libre de Parc Informatique
     * SPDX-License-Identifier: GPL-3.0-or-later
     * SPDX-FileCopyrightText: 2015-2026 Teclib' and contributors.
     */

    import {ref} from "vue";
    import KBTranslationModeToolbar from "./KBTranslationModeToolbar.vue";
    import KBArticleIllustration from "./KBArticleIllustration.vue";
    import KBArticleContent from "./KBArticleContent.vue";
    import KBArticleInfo from "./KBArticleInfo.vue";
    import KBArticleFooter from "./KBArticleFooter.vue";

    const props = defineProps({
        article: {
            type: Object,
            required: true
        },
        canEdit: {
            type: Boolean,
            required: true
        },
        canComment: {
            type: Boolean,
            required: true
        },
        parentArticleId: {
            type: Number,
            required: false
        },
        // The default language of the article
        defaultLanguage: {
            type: String,
            required: true
        },
        // Array of languages that the article has been translated into
        existingTranslationLanguages: {
            type: Array,
            required: true
        },
    });

    const editModeEnabled = ref(false);
    const translationModeEnabled = ref(false);

    function saveArticle() {

    }
</script>

<template>
    <div>
        <div class="d-flex mx-n2 my-n2">
            <article class="col-12 ms-0 px-4 py-3 kb-article">
                <KBTranslationModeToolbar v-if="translationModeEnabled" :articleId="article.id" @exitTranslationMode="translationModeEnabled = false"></KBTranslationModeToolbar>
                <div class="d-flex align-items-center mb-1 mt-1">
                    <KBArticleIllustration v-if="article.illustration || editModeEnabled" :article="article"></KBArticleIllustration>
                    <h1 v-if="!editModeEnabled" class="fw-bold mb-0" v-text="article.subject"></h1>
                    <input v-else type="text" class="h1 fw-bold mb-0 form-control form-control-sm border-0 p-0" :value="article.subject" :placeholder="__('Untitled article')" :spellcheck="true" :maxlength="255">
                    <div class="ms-auto d-flex align-items-center gap-2">
                        <div v-if="canEdit && article.id" class="dropdown">
                            <button type="button" class="btn btn-outline-secondary"
                                    data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                    aria-expanded="false" :aria-label="__('Share')">
                                <i class="ti ti-share me-1" aria-hidden="true"></i>
                                {{ __('Share') }}
                            </button>
                            <div class="dropdown-menu dropdown-menu-end p-3" style="min-width: 360px;" data-bs-popper="static">
                            </div>
                        </div>

                        <div v-if="canEdit" class="kb-editor-actions d-flex gap-2">
                            <button v-if="!editModeEnabled" type="button" class="btn btn-outline-secondary" @click="editModeEnabled = true">
                                <i class="ti ti-edit me-1" aria-hidden="true"></i>
                                {{ __('Edit') }}
                            </button>
                            <button v-if="editModeEnabled" type="button" class="btn btn-primary" @click="saveArticle()">
                                <i class="ti ti-device-floppy me-1" aria-hidden="true"></i>
                                {{ __('Save') }}
                            </button>
                            <button v-if="editModeEnabled" type="button" class="btn btn-outline-secondary" v-text="__('Cancel')"
                                    @click="editModeEnabled = false"></button>
                        </div>
                        <div v-if="article.actions.length > 0" class="dropdown cursor-pointer d-flex align-items-center">
                            <button type="button" class="btn btn-icon border-0 bg-transparent p-0 text-reset lh-1"
                                    data-bs-toggle="dropdown" aria-expanded="false"
                                    :aria-label="__('More actions')" :title="__('More actions')">
                                <i class="ti ti-dots-vertical" aria-hidden="true"></i>
                                <ul class="dropdown-menu" data-bs-popper="none">
<!--                                    <li v-for="action in article.actions" :key="action.id">-->
<!--                                        <a class="dropdown-item" :href="action.link_url" v-text="action.name"></a>-->
<!--                                    </li>-->
                                </ul>
                            </button>
                        </div>
                    </div>
                </div>
                <KBArticleInfo :article="article" :canComment="canComment" :editModeEnabled="editModeEnabled"></KBArticleInfo>
                <KBArticleContent :article="article" :editModeEnabled="editModeEnabled"></KBArticleContent>
                <KBArticleFooter :article="article" :editModeEnabled="editModeEnabled" :canEdit="canEdit"></KBArticleFooter>
            </article>
        </div>
        <div v-if="!article.id" class="px-4 mb-2 d-flex">
            <button class="btn btn-primary ms-auto" data-glpi-kb-add-article>
                <i class="ti ti-plus me-2" aria-hidden="true"></i>
                <span v-text="__('Add article')"></span>
            </button>
        </div>
        <!-- TODO offcanvas sidebar -->
    </div>
</template>

<style scoped>

</style>
