<script setup>
    /*!
     * GLPI - Gestionnaire Libre de Parc Informatique
     * SPDX-License-Identifier: GPL-3.0-or-later
     * SPDX-FileCopyrightText: 2015-2026 Teclib' and contributors.
     */
    import {computed, defineAsyncComponent, ref} from "vue";

    const props = defineProps({
        article: {
            type: Object,
            required: true
        },
        editModeEnabled: {
            type: Boolean,
            required: true
        },
        canEdit: {
            type: Boolean,
            required: true
        }
    });

    const showChildrenTab = computed(() => props.article.child_articles.length > 0);
    const showDocumentsTab = computed(() => props.article.can_add_documents || props.article.documents.length > 0);
    const showItemsTab = computed(() => props.article.can_link_items || props.article.related_items.length > 0);
    const tabs = computed(() => {
        const tabsArray = [];
        if (showChildrenTab.value) {
            tabsArray.push({
                key: 'children',
                icon: 'ti ti-list-tree',
                label: __('Sub-articles'),
                count: props.article.child_articles.length,
                tabComponent: defineAsyncComponent(() => import('./FooterTabs/KBChildArticleTab.vue'))
            });
        }
        if (showDocumentsTab.value) {
            tabsArray.push({
                key: 'documents',
                icon: 'ti ti-paperclip',
                label: __('Documents'),
                count: props.article.documents.length,
                tabComponent: defineAsyncComponent(() => import('./FooterTabs/KBDocumentsTab.vue'))
            });
        }
        if (showItemsTab.value) {
            tabsArray.push({
                key: 'items',
                icon: 'ti ti-link',
                label: __('Related items'),
                count: props.article.related_items.length,
                tabComponent: defineAsyncComponent(() => import('./FooterTabs/KBRelatedItemsTab.vue'))
            });
        }
        return tabsArray;
    });
    const activeTab = ref(tabs.value.length > 0 ? tabs.value[0] : null);
</script>

<template>
    <div v-if="article.id && (showChildrenTab || showDocumentsTab || showItemsTab)" class="mt-5">
        <ul class="nav nav-underline mb-3" role="tablist">
            <li v-for="tab in tabs" :key="tab.key" class="nav-item" role="presentation">
                <button class="nav-link" :class="activeTab.key === tab.key ? 'active' : ''" :id="'kb-' + tab.key + '-tab-btn'" data-bs-toggle="tab" :data-bs-target="'#kb-' + tab.key + '-tab'" type="button" role="tab" :aria-controls="'kb-' + tab.key + '-tab'" :aria-selected="activeTab.key === tab.key ? 'true' : 'false'">
                    <i :class="tab.icon + ' me-1'" aria-hidden="true"></i>{{ tab.label }}
                    <span class="badge bg-secondary bg-opacity-25 text-body ms-1">{{ tab.count }}</span>
                </button>
            </li>
        </ul>
        <div class="tab-content">
            <div v-for="tab in tabs" :key="tab.key" class="tab-pane fade" :class="activeTab.key === tab.key ? 'show active' : ''" :id="'kb-' + tab.key + '-tab'" role="tabpanel" :aria-labelledby="'kb-' + tab.key + '-tab-btn'">
                <component :is="tab.tabComponent" :article="article" :canEdit="canEdit"></component>
            </div>
        </div>
    </div>
</template>

<style scoped>
    .nav-link {
        color: var(--glpi-tabs-fg);
        border-color: var(--glpi-tabs-border-color);

        &.active {
            color: var(--glpi-tabs-active-fg);
            font-weight: bold;
        }
    }
</style>
