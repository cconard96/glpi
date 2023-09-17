<script setup>
    import SearchTokenizer from "../../../modules/SearchTokenizer/SearchTokenizer.js";
    import {computed} from "vue";

    const props = {
        tokenizer: {
            type: SearchTokenizer,
            required: true
        },
        tag_name: {
            type: String,
            required: true
        },
        selected_node: { //TODO Probably better to just pass the selected text
            type: Object
        },
        filter_on_type: {
            type: Boolean,
            required: true
        },
    };

    const autocomplete_values = computed(() => {
        const tag = props.tokenizer.allowed_tags[props.tag_name];
        if (tag === undefined) {
            return null;
        }
        const selected_text = props.selected_node ? props.selected_node.text().trim() : '';
        const tokens = props.tokenizer.tokenize(selected_text).getTaggedTerms();
        const current_term = (tokens.length > 0 ? tokens[0].term.trim() : '').toLowerCase();
        const all_autocomplete_values = props.tokenizer.getAutocomplete(props.tag_name);
        const autocomplete_values = [];
        $.each(all_autocomplete_values, (i, v) => {
            if ((props.filter_on_type && selected_text.length > 0) && !v.toLowerCase().startsWith(current_term)) {
                return; // continue
            }
            autocomplete_values.push(v);
        });
        return autocomplete_values;
    });
</script>

<template>
    <div class="popover">
        <ul class="list-group term-suggestions-list" v-if="autocomplete_values !== null">
            <li class="list-group-item list-group-item-action cursor-pointer px-3 py-1" v-for="v in autocomplete_values" :key="v" v-text="v"></li>
        </ul>
    </div>
</template>

<style scoped>

</style>
