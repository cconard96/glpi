<script setup>
    import {inject, onMounted} from 'vue';

    const props = defineProps({
        container_id: {
            type: String,
            default: `viewsubitem${Math.floor(Math.random() * 1000000000)}`,
        },
        datatable_id: {
            type: String,
        },
        can_create: {
            type: Boolean,
            default: false,
        },
        btn_create_label: {
            type: String,
            default: __('Add'),
        },
        type: {
            type: String,
            required: true,
        },
        parent_type: {
            type: String,
            required: true,
        },
        parent_id: {
            type: Number,
            required: true,
        },
    });
    const component = inject('component');
    component.autoBind = {
        global: {
            global: {
                type: props.type,
                parent_type: props.parent_type,
                parent_id: props.parent_id
            }
        }
    }

    function loadForm(id = -1) {
        component.loadHtml(`#${props.container_id}`, 'form', {id: id}).then(() => {
            console.log('loaded form');
            $(`#${props.container_id}`).find('form').attr('data-vue-form', 'subitem');
        });
    }

    onMounted(() => {
        //TODO This would be better handled by a component that wraps both the form and the datatable when they are meant to be used together
        if (props.datatable_id !== undefined) {
            $(document).on('click', `#${props.datatable_id} tbody tr`, (e) => {
                if ($(e.target).closest('td').find('.massive_action_checkbox').length > 0) {
                    return;
                }
                const row = $(e.target).closest('tr');
                loadForm(row.attr('data-id'));
            });
        }
    });
</script>

<template>
    <div>
        <div :id="container_id"></div>
        <div v-if="can_create" class="text-center mt-1 mb-3">
            <button class="btn btn-primary" type="button" @click="loadForm()" v-text="btn_create_label"></button>
        </div>
    </div>
</template>

<style scoped>

</style>
