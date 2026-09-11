<script setup>
    /*!
     * GLPI - Gestionnaire Libre de Parc Informatique
     * SPDX-License-Identifier: GPL-3.0-or-later
     * SPDX-FileCopyrightText: 2015-2026 Teclib' and contributors.
     */

    import {useId, ref} from "vue";

    const props = defineProps({
        id: {
            type: String,
            required: true
        },
        showBackdrop: {
            type: Boolean,
            default: true
        },
        allowEmptySelection: {
            type: Boolean,
            default: false
        },
    });

    const RESULTS_PER_PAGE = 30;

    const pickIconButtonId = useId();
    const pickIconPaneId = useId();
    const uploadIconPaneId = useId();
    const uploadIconButtonId = useId();
    const searchQuery = ref('');
    const currentPage = ref(1);
</script>

<template>
    <div :id="id" :data-bs-backdrop="showBackdrop">
        <div class="modal-dialog rounded">
            <div class="modal-content">
                <div class="px-4 pt-2 d-flex">
                    <div class="nav nav-underline" role="tablist">
                        <div class="nav-item" role="presentation">
                            <button
                                type="button"
                                class="nav-link pointer active"
                                role="tab"
                                :id="pickIconButtonId"
                                data-bs-toggle="tab"
                                :data-bs-target="`#${pickIconPaneId}`"
                                :aria-controls="pickIconPaneId"
                                aria-selected="true"
                            >
                            <span class="d-flex align-items-center">
                                <i class="ti ti-photo-scan fa-lg me-2" aria-hidden="true"></i>
                                {{ __("Pick an illustration") }}
                            </span>
                            </button>
                        </div>
                        <div class="nav-item" role="presentation">
                            <button
                                type="button"
                                class="nav-link pointer"
                                role="tab"
                                :id="uploadIconButtonId"
                                data-bs-toggle="tab"
                                :data-bs-target="`#${uploadIconPaneId}`"
                                :aria-controls="uploadIconPaneId"
                                aria-selected="false"
                            >
                            <span class="d-flex align-items-center">
                                <i class="ti ti-file-upload fa-lg me-2" aria-hidden="true"></i>
                                {{ __("Upload your own illustration") }}
                            </span>
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn-close ms-auto align-self-center" data-bs-dismiss="modal" :aria-label="__('Close')"></button>
            </div>
            <div class="modal-body tab-content">
                <div :id="pickIconPaneId" class="tab-pane fade active show" role="tabpanel" :aria-labelledby="pickIconButtonId">
                    <div class="input-icon mb-3">
                        <span class="input-icon-addon">
                            <i class="ti ti-search" aria-hidden="true"></i>
                            <span class="spinner-border spinner-border d-none" role="status" aria-hidden="true" data-glpi-icon-picker-filter-loading-icon></span>
                        </span>
                        <input type="text" class="form-control" :placeholder="__('Search')" :aria-label="__('Search')" v-model="searchQuery"/>
                    </div>
                    <div>
                        <!-- TODO Results grid -->
                    </div>
                </div>
                <div :id="uploadIconPaneId" class="tab-pane fade" role="tabpanel" :aria-labelledby="uploadIconButtonId">
                    <!-- TODO File input -->
                    <div class="d-flex justify-content-end w-100 mt-3">
                        <button type="button" class="btn btn-primary">
                            {{ __("Use selected file") }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
</template>

<style scoped>

</style>
