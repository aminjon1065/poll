<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
</script>
<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <slot />
            </div>
        </AppContent>
    </AppShell>
</template>

<style scoped></style>
