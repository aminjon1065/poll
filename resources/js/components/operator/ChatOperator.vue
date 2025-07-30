<script setup lang="ts">
import { Message } from '@/types';
import { formatDistanceToNow } from 'date-fns';
import { ru } from 'date-fns/locale';
import { Check, CheckCheck, Clock } from 'lucide-vue-next';

const props = defineProps<{
    message: Message;
    editingMessageId: number | null;
    editedContent: string;
    isClosed: boolean;
    saveEdit: (messageId: number, content: string) => void;
    cancelEdit: () => void;
    startEditing: (message: Message) => void;
}>();
</script>

<template>
    <div :class="[message.sender_type === 'operator' ? 'flex justify-end' : 'flex justify-start', 'group mb-2']">
        <div
            :class="[
                message.sender_type === 'operator' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800',
                'max-w-[70%] rounded-lg p-3 shadow-sm',
                message.status === 'sent' ? 'opacity-70' : '',
            ]"
        >
            <div v-if="editingMessageId === message.id && message.sender_type === 'operator'" class="animate-fade-in space-y-2">
                <input
                    v-model="editedContent"
                    type="text"
                    class="w-full rounded-md border border-gray-300 p-2 text-gray-800 focus:border-blue-500 focus:ring-blue-500"
                    @keydown.enter.prevent="saveEdit(message.id, editedContent)"
                />
                <div class="flex gap-2">
                    <button
                        @click="saveEdit(message.id, editedContent)"
                        class="rounded-md bg-blue-600 px-3 py-1 text-sm text-white transition-colors hover:bg-blue-700"
                    >
                        Сохранить
                    </button>
                    <button
                        @click="cancelEdit"
                        class="rounded-md border border-gray-300 px-3 py-1 text-sm text-gray-800 transition-colors hover:bg-gray-100"
                    >
                        Отмена
                    </button>
                </div>
            </div>
            <div v-else>
                <p class="break-words">{{ message.content }}</p>
                <div class="mt-1 flex items-center gap-1 text-xs opacity-80" v-if="message.sender_type === 'operator'">
                    <span v-if="message.status === 'sent'">
                        <Clock class="h-3 w-3" />
                    </span>
                    <span v-else-if="message.status === 'delivered'">
                        <Check class="h-3 w-3" />
                    </span>
                    <span v-else-if="message.status === 'read'">
                        <CheckCheck class="h-3 w-3" />
                    </span>
                    <span>
                        {{
                            formatDistanceToNow(new Date(message.created_at), {
                                addSuffix: true,
                                locale: ru,
                            })
                        }}
                        <span v-if="message.is_edited" class="italic"> (ред.)</span>
                    </span>
                </div>
                <button
                    v-if="message.sender_type === 'operator' && editingMessageId !== message.id && !isClosed"
                    @click="startEditing(message)"
                    class="mt-1 hidden text-xs text-blue-200 transition-colors group-hover:block hover:text-blue-100"
                >
                    Редактировать
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped></style>
