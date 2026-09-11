<script setup>
    /*!
     * GLPI - Gestionnaire Libre de Parc Informatique
     * SPDX-License-Identifier: GPL-3.0-or-later
     * SPDX-FileCopyrightText: 2015-2026 Teclib' and contributors.
     */

    import {useTemplateRef, ref, onMounted} from "vue";

    const props = defineProps({
        articleId: {
            type: Number,
            required: true
        },
    })
    const emits = defineEmits(['exit-translation-mode']);

    const languageDropdown = useTemplateRef('languageDropdown');
    /** @var {{code: string, name: string, has_translation: boolean}[]} */
    const translationLanguages = ref([]);

    onMounted(() => {
        loadTranslationLanguages();
    });

    function deleteTranslation() {

    }

    function saveTranslation() {

    }

    function switchTranslationLanguage() {

    }

    function loadTranslationLanguages() {
        fetch(`${CFG_GLPI.root_doc}/Knowbase/KnowbaseItem/${props.articleID}/Languages`)
            .then(response => response.json())
            .then(data => translationLanguages.value = data.languages)
            .catch(error => console.error('Error loading translation languages:', error));
    }
</script>

<template>
    <div class="alert alert-info d-none align-items-center mb-3 gap-1" data-testid="translation-mode-alert" data-glpi-kb-translation-alert>
        <i class="ti ti-language me-2 fs-3" aria-hidden="true"></i>
        <span v-text="__('Translation mode enabled, currently editing')"></span>
        <select ref="languageDropdown" class="form-select form-select-sm d-inline-block w-auto ms-2 me-auto" @change="switchTranslationLanguage()">
            <optgroup v-if="translationLanguages.filter(lang => lang.has_translation).length > 0" :label="__('Existing translations')">
                <option v-for="lang in translationLanguages.filter(lang => lang.has_translation)" :key="lang.code" :value="lang.code">
                    {{ lang.name }}
                </option>
            </optgroup>
            <optgroup v-if="translationLanguages.filter(lang => !lang.has_translation).length > 0" :label="__('Add new translation')">
                <option v-for="lang in translationLanguages.filter(lang => !lang.has_translation)" :key="lang.code" :value="lang.code">
                    {{ lang.name }}
                </option>
            </optgroup>
        </select>
        <button type="button" class="btn btn-sm btn-ghost-danger d-none" @click="deleteTranslation()" :title="__('Delete this translation')">
            <i class="ti ti-trash" aria-hidden="true"></i>
        </button>
        <button type="button" class="btn btn-sm btn-primary me-2" @click="saveTranslation()">
            <i class="ti ti-device-floppy me-1" aria-hidden="true"></i>{{ __('Save') }}
        </button>
        <button type="button" class="btn-close" @click="emits('exit-translation-mode')" aria-label="{{ __('Close') }}"></button>
    </div>
</template>

<style scoped>

</style>
