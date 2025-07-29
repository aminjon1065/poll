<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Check, CheckCheck, Clock } from 'lucide-vue-next';
import type { Message } from '@/types';

const props = defineProps<{
    message: Message;
    isOwnMessage: boolean;
}>();

const emit = defineEmits<{
    (e: 'edit', messageId: number, content: string): void;
}>();

const isEditing = ref(false);
const editedContent = ref(props.message.content);

const statusText = computed(() => {
    switch (props.message.status) {
        case 'sent':
            return 'Отправлено';
        case 'delivered':
            return 'Доставлено';
        case 'read':
            return 'Прочитано';
        default:
            return '';
    }
});

const startEditing = () => {
    isEditing.value = true;
};

const saveEdit = () => {
    if (editedContent.value.trim()) {
        emit('edit', props.message.id, editedContent.value);
        isEditing.value = false;
    }
};

const cancelEdit = () => {
    isEditing.value = false;
    editedContent.value = props.message.content;
};
</script>

<template>
    <div :class="message.sender_type === 'client' ? 'flex justify-end' : 'flex justify-start'" class="group">
        <div
            :class="[
                message.sender_type === 'client' ? 'bg-blue-200' : 'bg-gray-200',
                'max-w-[70%] rounded-lg p-3 shadow-sm',
                message.status === 'sent' ? 'opacity-70' : '',
                'animate-slide-in'
            ]"
        >
            <div v-if="isEditing && isOwnMessage" class="space-y-2 animate-fade-in">
                <Input v-model="editedContent" class="rounded-md border-gray-300 text-gray-800 focus:border-blue-500 focus:ring-blue-500" />
                <div class="flex gap-2">
                    <Button @click="saveEdit" size="sm" class="bg-blue-600 hover:bg-blue-700 text-white">Сохранить</Button>
                    <Button @click="cancelEdit" size="sm" variant="outline" class="border-gray-300 hover:bg-gray-100">Отмена</Button>
                </div>
            </div>
            <div v-else>
                <p class="break-words">{{ message.content }}</p>
                <div class="mt-1 flex items-center gap-1 text-xs text-gray-600" v-if="message.sender_type === 'client'">
                    <span v-if="message.status === 'sent'">
                        <Clock class="h-3 w-3" />
                        {{ statusText }}
                    </span>
                    <span v-else-if="message.status === 'delivered'">
                        <Check class="h-3 w-3" />
                        {{ statusText }}
                    </span>
                    <span v-else-if="message.status === 'read'">
                        <CheckCheck class="h-3 w-3" />
                        {{ statusText }}
                    </span>
                    <span v-if="message.is_edited" class="italic"> (ред.)</span>
                </div>
                <Button
                    v-if="isOwnMessage && !isEditing"
                    @click="startEditing"
                    size="sm"
                    class="mt-1 text-blue-500 hover:text-blue-600 hidden group-hover:block"
                >
                    Редактировать
                </Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animate-slide-in {
    animation: slideIn 0.3s ease-out;
}
@keyframes slideIn {
    from {
        transform: translateY(10px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.animate-fade-in {
    animation: fadeIn 0.2s ease-out;
}
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}
</style>
