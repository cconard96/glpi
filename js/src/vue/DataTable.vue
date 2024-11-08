<script setup>
    //TODO This is not feature complete compared to the datatable Twig template
    const props = defineProps({
        id: {
            type: String,
            default: `datatable${Math.floor(Math.random() * 1000000000)}`,
        },
        show_massive_actions: {
            type: Boolean,
            default: false,
        },
        massive_action_params: {
            type: Object,
            default: () => ({}),
        },
        columns: {
            type: Object,
            required: true,
        },
        entries: {
            type: Array,
            default: () => [],
        },
        total_count: {
            type: Number,
            required: true,
        },
        table_class_style: {
            type: String,
            default: 'table-hover',
        },
    });
</script>

<template>
    <table :id="props.id" :class="`table ${props.table_class_style}`">
        <thead v-if="total_count > 0">
            <tr>
                <th v-if=" props.show_massive_actions" class="ma_column">
                    <div>
                        <input type="checkbox" class="form-check-input massive_action_checkbox" value="" :aria-label="__('Check all')"
                               :id="`checkall_${ props.massive_action_params.container}`"
                               @change="window.checkAsCheckboxes(this,  props.massive_action_params.container, '.massive_action_checkbox')">
                    </div>
                </th>
                <th v-for="(column, column_key) in props.columns" :key="column_key" v-html="column"></th>
            </tr>
        </thead>
        <tbody v-if="props.total_count <= 0">
            <tr>
                <td>
                    <div class="alert alert-info" v-text="__('No data')"></div>
                </td>
            </tr>
        </tbody>
        <tbody v-if="props.total_count > 0">
            <tr v-for="entry in props.entries" :key="entry.id" :data-id="entry.id">
                <td v-if=" props.show_massive_actions && !entry.skip_ma" class="ma_column">
                    <div>
                        <input type="checkbox" class="form-check-input massive_action_checkbox" value="1" :aria-label="__('Select item')"
                               data-glpicore-ma-tags="common" :name="`item[${entry.itemtype}][${entry.id}]`">
                    </div>
                </td>
                <td v-for="(column, column_key) in props.columns" :key="column_key" :class="column.class" v-html="entry[column_key]"></td>
            </tr>
        </tbody>
    </table>
</template>

<style scoped>
    .ma_column {
        width: 30px;
    }
</style>
