<script setup>
    import DashboardGrid from "./DashboardGrid.vue";
</script>

<template>
    <Suspense>
        <template #default>
            <DashboardGrid v-bind="$attrs" />
        </template>
        <template #fallback>
            <div class="d-flex justify-content-center align-items-center" style="height: 200px;">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </template>
    </Suspense>
</template>

<style scoped>

</style>
