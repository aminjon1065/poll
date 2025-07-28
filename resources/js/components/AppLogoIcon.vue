<script setup lang="ts">
import logo from '@/assets/logo.png';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img :src="logo" alt="Logo" class="bg-white dark:bg-black" />
</template>
