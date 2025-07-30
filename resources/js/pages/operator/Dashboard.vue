<script setup lang="ts">
import OperatorLayout from '@/layouts/OperatorLayout.vue';
import type { Chat } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    chats: Chat[];
}>();
</script>

<template>
    <Head title="Dashboard" />
    <OperatorLayout :chats="props.chats">
        <div v-if="props.chats.length > 0">Выберите чат для общения с клиентом</div>
        <div v-else>Пока нет активных чатов</div>
    </OperatorLayout>
</template>

<style scoped></style>
