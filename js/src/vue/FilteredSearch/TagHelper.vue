<script setup>
    import SearchTokenizer from "../../../modules/SearchTokenizer/SearchTokenizer.js";
    import {computed} from "vue";

    const props = defineProps({
        tokenizer: {
            type: SearchTokenizer,
            required: true
        },
        selected_text: {
            type: String,
            default: ''
        },
        filter_on_type: {
            type: Boolean,
            default: true
        },
    });

    const tags_to_show = computed(() => {
        const all_tags = props.tokenizer.allowed_tags;
        let selected_text = props.selected_text.trim();
        // Match either:
        // [^\s"]+ - one or more characters that are not whitespace or double quotes
        // OR
        // "[^"]*" - a double quote, followed by zero or more characters that are not double quotes, followed by a double quote
        // The pattern is wrapped in a non-capturing group, and the group is matched one or more times
        //TODO Why didn't I just feed the text to the tokenizer and get the term of the last token?
        const selected_phrases = selected_text.match(/(?:[^\s"]+|"[^"]*")+/g);
        selected_text = (selected_phrases ? selected_phrases[selected_phrases.length - 1] : '').toLowerCase();
        const tags_to_show = {};

        $.each(all_tags || {}, (name, info) => {
            if ((props.filter_on_type && selected_text.length > 0) && !name.toLowerCase().startsWith(selected_text)) {
                return; // continue
            }
            const tag_info = {
                description: info.description || '',
                supported_prefixes: {}
            };

            $.each(info.supported_prefixes, (i, prefix) => {
                const custom_prefix = props.tokenizer.options.custom_prefixes[prefix];
                let label = custom_prefix ? (custom_prefix.label || prefix) : prefix;
                if (prefix === props.tokenizer.EXCLUSION_PREFIX) {
                    label = __('Exclude');
                }
                tag_info.supported_prefixes[prefix] = {
                    label: label,
                };
            });
            tags_to_show[name] = tag_info;
        });

        return tags_to_show;
    });
</script>

<template>
    <div class="popover">
        <ul class="list-group tags-list">
            <li class="list-group-item list-group-item-action cursor-pointer px-3 py-1" v-for="(tag_info, tag_name) in tags_to_show" :key="tag_name">
                <div class="d-flex flex-grow-1 justify-content-between">
                    <b v-text="tag_name"></b>
                    <span>
                        <button type="button" :class="`btn btn-outline-secondary btn-sm tag-prefix ${i > 0 ? 'ms-1' : ''}`"
                                :data-prefix="prefix" :title="prefix_info.label" v-text="prefix"
                                v-for="(prefix_info, prefix, i) in tag_info.supported_prefixes" :key="prefix"></button>
                    </span>
                </div>
                <div class="text-muted fst-italic" v-text="tag_info.description"></div>
            </li>
        </ul>
    </div>
</template>

<style scoped>

</style>
