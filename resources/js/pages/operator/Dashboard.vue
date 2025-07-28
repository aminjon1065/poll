<script setup lang="ts">
import OperatorLayout from '@/layouts/OperatorLayout.vue';
import type { Chat } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    chats: Chat;
}>();
</script>

<template>
    <Head title="Dashboard" />
    <OperatorLayout :chats="props.chats">
        <div>
            Lorem ipsum dolor sit amet, consectetur adipisicing elit. Blanditiis iste maxime perferendis?
        </div>
    </OperatorLayout>
</template>

<style scoped></style>
