<script setup lang="ts">
import { ref, computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    <div :class="message.sender_type === 'client' ? 'text-right' : 'text-left'">
        <div class="inline-block p-2 rounded-lg bg-gray-100" :class="{ 'opacity-50': message.status === 'sent' }">
            <div v-if="isEditing && isOwnMessage">
                <Input v-model="editedContent" class="mb-2" />
                <div class="flex gap-2">
                    <Button @click="saveEdit" size="sm">Сохранить</Button>
                    <Button @click="cancelEdit" size="sm" variant="outline">Отмена</Button>
                </div>
            </div>
            <div v-else>
                {{ message.content }}
                <span class="text-sm text-gray-500 block">{{ statusText }} <span v-if="message.updated_at !== message.created_at">(ред.)</span></span>
                <Button v-if="isOwnMessage && !isEditing" @click="startEditing" size="sm" class="mt-1">Редактировать</Button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.text-left .bg-gray-100 {
    background-color: #e5e7eb;
}
.text-right .bg-gray-100 {
    background-color: #bfdbfe;
}
</style>
