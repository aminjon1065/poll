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
    <div :class="props.message.sender_type === 'client' ? 'flex justify-end' : 'flex justify-start'" class="group">
        <div
            :class="[
                props.message.sender_type === 'client' ? 'bg-blue-200' : 'bg-gray-200',
                'max-w-[70%] rounded-lg p-3 shadow-sm',
                props.message.status === 'sent' ? 'opacity-70' : '',
            ]"
        >
            <div v-if="isEditing && isOwnMessage" class="space-y-2">
                <Input
                    v-model="editedContent"
                    class="rounded-md border-gray-300 text-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                />
                <div class="flex gap-2">
                    <Button
                        @click="saveEdit"
                        size="sm"
                        class="bg-blue-600 hover:bg-blue-700 text-white"
                    >
                        Сохранить
                    </Button>
                    <Button
                        @click="cancelEdit"
                        size="sm"
                        variant="outline"
                        class="border-gray-300 hover:bg-gray-100"
                    >
                        Отмена
                    </Button>
                </div>
            </div>
            <div v-else>
                <p class="break-words">{{ props.message.content }}</p>
                <div v-if="props.message.sender_type === 'client'" class="mt-1 flex items-center gap-1 text-xs text-gray-600">
                    <span v-if="props.message.status === 'sent'" class="flex items-center gap-1">
                        <Clock class="h-3 w-3" />
                        {{ statusText }}
                    </span>
                    <span v-else-if="props.message.status === 'delivered'" class="flex items-center gap-1">
                        <Check class="h-3 w-3" />
                        {{ statusText }}
                    </span>
                    <span v-else-if="props.message.status === 'read'" class="flex items-center gap-1">
                        <CheckCheck class="h-3 w-3 text-blue-600" />
                        {{ statusText }}
                    </span>
                    <span v-if="props.message.is_edited" class="italic">(ред.)</span>
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
