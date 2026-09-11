<script setup>
    /*!
     * GLPI - Gestionnaire Libre de Parc Informatique
     * SPDX-License-Identifier: GPL-3.0-or-later
     * SPDX-FileCopyrightText: 2015-2026 Teclib' and contributors.
     */
    import {computed} from "vue";

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

    const usedDocumentIds = computed(() => {
        return props.article.documents.map(doc => doc.id);
    });
</script>

<template>
    <div class="d-flex flex-wrap gap-2 mb-2">
        <span v-for="document in article.documents" :key="document.id" class="kb-item-badge badge d-flex align-items-center gap-2">
            <a :href="document.download_url" target="_blank" class="d-flex align-items-center gap-2 text-decoration-none">
                <i :class="`ti ${document.icon_class} ${document.color_class}`" aria-hidden="true"></i>
                <span>{{ document.name }}</span>
            </a>
            <button type="button" class="kb-unlink-item btn-close" :title="__('Unlink document')" :aria-label="__('Unlink document')"></button>
        </span>
    </div>
    <button v-if="article.can_add_documents" type="button" class="btn btn-sm btn-outline-secondary mt-2">
        <i class="ti ti-plus me-1" aria-hidden="true"></i>{{ __('Add Document') }}
    </button>
    <div class="modal fade" id="kb-add-document-modal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0 pt-3">
                    <ul class="nav nav-underline kb-modal-nav flex-fill mb-0" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="kb-modal-upload-tab" data-bs-toggle="tab"
                                data-bs-target="#kb-modal-upload-pane" type="button" role="tab"
                                aria-controls="kb-modal-upload-pane" aria-selected="true"
                            >
                                <i class="ti ti-upload me-1" aria-hidden="true"></i>{{ __('Upload a file') }}
                            </button>
                        </li>
                        <li v-if="article.document_idor_token" class="nav-item" role="presentation">
                            <button class="nav-link" id="kb-modal-link-tab"
                                data-bs-toggle="tab" data-bs-target="#kb-modal-link-pane"
                                type="button" role="tab"
                                aria-controls="kb-modal-link-pane" aria-selected="false"
                            >
                                <i class="ti ti-link me-1" aria-hidden="true"></i>{{ __('Link a document') }}
                            </button>
                        </li>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="tab-content">
                        <div
                            class="tab-pane fade show active"
                            id="kb-modal-upload-pane"
                            role="tabpanel"
                            aria-labelledby="kb-modal-upload-tab"
                        >
                            <form id="kb-document-upload-form" method="post">
                                <input type="hidden" name="itemtype" value="KnowbaseItem">
                                <input type="hidden" name="items_id" :value="article.id">

                                <!-- TODO {# File uploader component #} -->

                                <div class="invalid-feedback d-block mt-2" role="alert" id="kb-document-upload-error"></div>

                                <div class="mb-3 mt-3">
                                    <label for="kb-document-description" class="form-label">
                                        {{ __('Description') }}
                                        <span class="text-muted">({{ __('optional') }})</span>
                                    </label>
                                    <textarea
                                        class="form-control"
                                        id="kb-document-description"
                                        name="comment"
                                        rows="2"
                                        placeholder="{{ __('Add a description for these documents...') }}"
                                    ></textarea>
                                </div>

                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">
                                        {{ __('Cancel') }}
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-upload me-1" aria-hidden="true"></i>
                                        {{ __('Upload Documents') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div
                            v-if="article.document_idor_token"
                            class="tab-pane fade"
                            id="kb-modal-link-pane"
                            role="tabpanel"
                            aria-labelledby="kb-modal-link-tab"
                        >
                            <div class="mb-3">
                                <label for="kb-link-document-select" class="form-label">
                                    {{ __('Search for a document') }}
                                </label>
                                <select id="kb-link-document-select"
                                        class="form-select"></select>
                                <div class="invalid-feedback d-block mt-2" role="alert" id="kb-document-link-error"></div>
                            </div>

                            <div class="file-uploader-preview d-none">
                                <div class="file-uploader-list" role="list"></div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <button type="button" class="btn btn-ghost-secondary" data-bs-dismiss="modal">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="button" class="btn btn-primary">
                                    <i class="ti ti-link me-1" aria-hidden="true"></i>
                                    {{ __('Link Documents') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>

</style>
