<script setup lang="ts">
import { Message } from '@/types';

defineProps<{
    message: Message;
}>();
</script>

<template>
    <div class="flex" :class="message.sender_type === 'client' ? 'justify-end' : 'justify-start'">
        <div
            class="max-w-[80%] rounded-lg px-3 py-2 text-sm"
            :class="message.sender_type === 'client' ? 'bg-primary text-primary-foreground' : 'bg-muted'"
        >
            <p>{{ message.content }}</p>

            <!-- Галочки только на сообщениях клиента -->
            <div v-if="message.sender_type === 'client'" class="mt-1 text-right text-xs">
                <span v-if="message.status === 'read'" class="text-blue-500">✓✓</span>
                <span v-else-if="message.status === 'delivered'" class="text-gray-400">✓✓</span>
                <span v-else>✓</span>
            </div>
        </div>
    </div>
</template>
