<script setup>
    import SearchTokenizer from "../../../modules/SearchTokenizer/SearchTokenizer.js";
    import {ref} from "vue";

    const props = defineProps({
        original_input: {
            type: Object,
            required: true
        },
        backspace_action: {
            type: String,
            default: 'edit'
        },
        tokenizer_options: {
            type: Object,
            default: () => {}
        },
        filter_on_type: {
            type: Boolean,
            default: true
        },
        input_options: {
            type: Object,
            default: () => {}
        },
        allowed_tags: {
            type: Object,
            default: () => {}
        },
        drop_unallowed_tags: {
            type: Boolean,
            default: false
        },
    });

    function applyInputOptions() {
        let new_attrs = {};

        const displayed = $(displayed_input.value);

        if (props.input_options.attributes !== undefined) {
            if (typeof props.input_options.attributes === 'object') {
                new_attrs = props.input_options.attributes;
            } else if (props.input_options.attributes === 'copy') {
                const original_attr = props.original_input.get(0).attributes;
                for (let i = 0; i < original_attr.length; i++) {
                    // Get only non-data attributes
                    if (!original_attr[i].name.startsWith('data-') && original_attr[i].name !== 'class') {
                        new_attrs[original_attr[i].name] = original_attr[i].value;
                    }
                }
            }
        }

        let new_data = {};
        let old_data_attrs = {};
        if (props.input_options.data !== undefined) {
            if (typeof props.input_options.data === 'object') {
                new_data = props.input_options.data;
            } else if (props.input_options.data === 'copy') {
                new_data = props.original_input.data();
                const original_attr = props.original_input.get(0).attributes;
                // Get data attributes in case they aren't in jQuery data
                for (let i = 0; i < original_attr.length; i++) {
                    // Get only data attributes
                    if (original_attr[i].name.startsWith('data-')) {
                        old_data_attrs[original_attr[i].name] = original_attr[i].value;
                    }
                }
            }
        }

        // Add data attributes. We don't use $.data() because having the DOM attribute may be needed and using $.data doesn't add them.
        // Information from $.data will override any data attributes of the same name
        new_attrs = Object.assign(old_data_attrs, Object.keys(new_data).reduce((obj, key) => {
            obj['data-' + key] = new_data[key];
            return obj;
        }, new_attrs));

        // Apply attributes including data attributes
        displayed.attr(new_attrs);

        // Apply classes
        if (props.input_options.classes !== undefined) {
            if (Array.isArray(props.input_options.classes)) {
                displayed.value.addClass(props.input_options.classes.join(' '));
            } else if (props.input_options.classes === 'copy') {
                displayed.value.addClass(props.original_input.attr('class'));
            }
        }
    }

    function tagifySelectedNode() {
        const selected_node = $(getSelectedNode());
        if (selected_node && isSelectionUntagged()) {
            return tagifyInputNode(selected_node);
        }
        return null;
    }

    /**
     * @param {SearchToken} token
     */
    function tokenToTagHtml(token) {
        const tag_display = token.tag ? `<b>${token.exclusion ? this.tokenizer.EXCLUSION_PREFIX : ''}${token.prefix ? token.prefix : ''}${token.tag}</b>:` : '';
        let tag_color_override = null;
        if (this.tokenizer.options.custom_prefixes[token.prefix]) {
            tag_color_override = this.tokenizer.options.custom_prefixes[token.prefix].token_color || null;
        } else if (token.exclusion) {
            tag_color_override = '#80000080';
        }
        const dark_mode = $('html').css('--is-dark').trim() === 'true';
        const text_color = $(document.body).css('color');
        let style_overrides = '';
        if (!token.tag) {
            tag_color_override = text_color;
        }
        if (dark_mode) {
            tag_color_override = tag_color_override || '#b3b3b3';
            // Remove alpha from hex color
            if (tag_color_override.indexOf('#') === 0) {
                tag_color_override = tag_color_override.replace(/[^#]*#([0-9a-f]{6})([0-9a-f]{2})?/i, '#$1');
            }
            style_overrides = tag_color_override ? `style="border-color: ${tag_color_override} !important; background-color: unset !important;"` : '';
        } else {
            style_overrides = tag_color_override ? `style="background-color: ${tag_color_override} !important"` : '';
        }
        return `<span class="search-input-tag badge bg-secondary me-1" contenteditable="false" data-tag="${token.tag}" ${style_overrides}>
                  <span class="search-input-tag-value" contenteditable="false">${tag_display}${escapeMarkupText(token.term) || ''}</span>
                  <i class="ti ti-x cursor-pointer ms-1" title="${__('Delete')}" contenteditable="false"></i>
               </span>`;
    }

    const tokenizer = new SearchTokenizer(props.allowed_tags, props.drop_unallowed_tags, props.tokenizer_options);
    const displayed_input = ref(null);
    applyInputOptions();

</script>

<template>
    <div class="form-control search-input d-flex overflow-auto" tabindex="0" ref="displayed_input">
        <span class="search-input-tag-input flex-grow-1" contenteditable="true"></span>
    </div>
</template>

<style scoped>

</style>
