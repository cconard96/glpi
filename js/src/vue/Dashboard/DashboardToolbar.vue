<script setup>
    import {onMounted, ref} from "vue";

    const props = defineProps({
        dashboard_name: {
            type: String,
            required: true,
        },
        context: {
            type: String,
            required: true,
        },
    });

    const dashboard_list = ref({});
    onMounted(() => {
        $.ajax({
            url: `${CFG_GLPI.root_doc}/ajax/dashboard.php`,
            method: 'GET',
            data: {
                action: 'list_dashboards',
                context: props.context,
            }
        }).then(r => {
            dashboard_list.value = r;
        });
    });
</script>

<template>
    <div class="toolbar left-toolbar">
        <div class="change-dashboard d-flex">
            <select class="form-select">
                <option v-for="(name, id) in dashboard_list" :key="id" :value="id" :selected="name === dashboard_name">{{ name }}</option>
            </select>
            <i class="btn btn-sm btn-icon btn-ghost-secondary ti ti-plus fs-toggle add-dashboard" data-bs-toggle="tooltip" :title="__('Add a new dashboard')"></i>
        </div>
        <div class="edit-dashboard-properties">
            <input type="text" class="dashboard-name form-control" :value="dashboard_name" size="1">
            <i class="btn btn-ghost-secondary ti ti-device-floppy ms-1 save-dashboard-name" data-bs-toggle="tooltip" data-bs-placement="bottom" :title="_x('button', 'Save')"></i>
            <span class="display-message"></span>
        </div>
    </div>
    <div class="toolbar right-toolbar">
        <i class="btn btn-sm btn-icon btn-ghost-secondary ti ti-refresh auto-refresh" data-bs-toggle="tooltip" data-bs-placement="bottom" :title="__('Toggle auto-refresh')"></i>
        <i class="btn btn-sm btn-icon btn-ghost-secondary ti ti-moon night-mode" data-bs-toggle="tooltip" data-bs-placement="bottom" :title="__('Toggle night mode')"></i>
        <i class='btn btn-sm btn-icon btn-ghost-secondary ti ti-copy fs-toggle clone-dashboard' data-bs-toggle='tooltip' data-bs-placement='bottom' :title="__('Clone this dashboard')"></i>
        <i class='btn btn-sm btn-icon btn-ghost-secondary ti ti-share fs-toggle open-embed' data-bs-toggle='tooltip' data-bs-placement='bottom' :title="__('Share or embed this dashboard')"></i>
        <i class='btn btn-sm btn-icon btn-ghost-secondary ti ti-trash fs-toggle delete-dashboard' data-bs-toggle='tooltip' data-bs-placement='bottom' :title="__('Delete this dashboard')"></i>
        <i class='btn btn-sm btn-icon btn-ghost-secondary ti ti-edit fs-toggle edit-dashboard' data-bs-toggle='tooltip' data-bs-placement='bottom' :title="__('Toggle edit mode')"></i>
        <i class='btn btn-outline-secondary ti ti-filter fs-toggle filter-dashboard' data-bs-toggle='tooltip' data-bs-placement='bottom' :title="__('Toggle filter mode')"></i>
        <i class='btn btn-sm btn-icon btn-ghost-secondary ti ti-maximize toggle-fullscreen' data-bs-toggle='tooltip' data-bs-placement='bottom' :title="__('Toggle fullscreen')"></i>
    </div>
</template>

<style scoped>

</style>
