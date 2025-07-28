<script setup lang="ts">
import OperatorLayout from '@/layouts/OperatorLayout.vue';
import type { Chat, Message } from '@/types';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';
import { ru } from 'date-fns/locale';
import { Check, CheckCheck, Clock, Send } from 'lucide-vue-next';
import { v4 as uuidv4 } from 'uuid';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    chat: Chat;
    chats: Chat[];
    auth: { user: { id: number; name: string } };
}>();

const messages = ref<Message[]>(props.chat.messages || []);
const messageInput = ref('');
const lastEventId = ref(0);
const isTyping = ref(false);
const polling = ref(true);
const typingTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const pendingMessages = ref<{ [key: string]: { content: string; timeout: ReturnType<typeof setTimeout> } }>({});
const editingMessageId = ref<number | null>(null);
const editedContent = ref('');

// Отправка сообщения
const sendMessage = async (content: string, uuid: string | null = null) => {
    if (!messageInput.value.trim()) return;

    const messageUuid = uuid ?? uuidv4();
    try {
        const response = await axios.post(route('operator.chat.send', props.chat.id), {
            content,
            uuid: messageUuid,
        });
        messages.value.push(response.data.message);

        if (!uuid) {
            pendingMessages.value[messageUuid] = {
                content,
                timeout: setTimeout(() => retryMessage(messageUuid, content), 10000),
            };
        }
        if (!uuid) messageInput.value = '';
        sendTypingEvent(false);
    } catch (error: any) {
        console.error('Ошибка отправки сообщения:', error);
        alert(error.response?.data?.error || 'Не удалось отправить сообщение');
    }
};

// Повторная отправка сообщения
const retryMessage = async (uuid: string, content: string) => {
    const message = messages.value.find((msg) => msg.uuid === uuid);
    if (message && message.status === 'sent') {
        console.log(`Повторная отправка сообщения ${uuid}`);
        await sendMessage(content, uuid);
    }
};

// Редактирование сообщения
const startEditing = (message: Message) => {
    editingMessageId.value = message.id;
    editedContent.value = message.content;
};

const saveEdit = async (messageId: number, content: string) => {
    if (!editedContent.value.trim() || !editingMessageId.value) return;

    try {
        const response = await axios.put(
            route('operator.chat.edit', {
                chat_id: props.chat.id,
                message_id: messageId,
            }),
            {
                content: content,
            },
        );
        const updatedMessage = response.data.message;
        const index = messages.value.findIndex((msg) => msg.id === editingMessageId.value);
        if (index !== -1) {
            messages.value[index] = updatedMessage;
        }
        editingMessageId.value = null;
        editedContent.value = '';
    } catch (error: any) {
        console.error('Ошибка редактирования сообщения:', error);
        alert(error.response?.data?.error || 'Не удалось отредактировать сообщение');
    }
};

const cancelEdit = () => {
    editingMessageId.value = null;
    editedContent.value = '';
};

// Отправка события "печатает"
const sendTypingEvent = async (typing: boolean) => {
    try {
        await axios.post(route('operator.chat.typing', props.chat.id), { typing });
    } catch (error) {
        console.error('Ошибка отправки события печатает:', error);
    }
};

// Обработка ввода текста
const handleInput = () => {
    if (typingTimeout.value) clearTimeout(typingTimeout.value);

    if (messageInput.value.trim()) {
        sendTypingEvent(true);
        typingTimeout.value = setTimeout(() => sendTypingEvent(false), 3000);
    } else {
        sendTypingEvent(false);
    }
};

// Отметка сообщений как прочитанных
const markMessagesAsRead = async () => {
    const unreadMessages = messages.value.filter((msg) => msg.sender_type === 'client' && msg.status === 'delivered').map((msg) => msg.id);
    if (unreadMessages.length) {
        try {
            await axios.post(route('operator.chat.read', props.chat.id), {
                message_ids: unreadMessages,
            });
        } catch (error) {
            console.error('Ошибка отметки сообщений как прочитанных:', error);
        }
    }
};

// Long-polling
const pollMessages = async () => {
    if (!polling.value) return;

    try {
        const response = await axios.get(route('operator.chat.poll', props.chat.id), {
            params: { last_event_id: lastEventId.value },
        });
        const { messages: newMessages, typing_events, last_event_id } = response.data;
        if (newMessages.length) {
            messages.value = newMessages;
            newMessages.forEach((msg:Message) => {
                if (msg.status !== 'sent' && pendingMessages.value[msg.uuid]) {
                    clearTimeout(pendingMessages.value[msg.uuid].timeout);
                    delete pendingMessages.value[msg.uuid];
                }
            });
            markMessagesAsRead();
        }
        if (typing_events.length) {
            const latestTypingEvent = typing_events[typing_events.length - 1];
            isTyping.value = latestTypingEvent.event_type === 'typing_start';
        }
        lastEventId.value = last_event_id;
    } catch (error) {
        console.error('Ошибка long-polling:', error);
    } finally {
        if (polling.value) {
            setTimeout(pollMessages, 100);
        }
    }
};

// Плавная прокрутка
const scrollAreaRef = ref<HTMLElement | null>(null);
watch(
    messages,
    async () => {
        await nextTick();
        if (scrollAreaRef.value) {
            scrollAreaRef.value.scrollTo({
                top: scrollAreaRef.value.scrollHeight,
                behavior: 'smooth',
            });
        }
        markMessagesAsRead();
    },
    { deep: true },
);

// Запуск и остановка polling
onMounted(() => {
    polling.value = true;
    pollMessages();
});
onUnmounted(() => {
    polling.value = false;
    if (typingTimeout.value) clearTimeout(typingTimeout.value);
    Object.values(pendingMessages.value).forEach(({ timeout }) => clearTimeout(timeout));
});
</script>

<template>
    <OperatorLayout :chats="props.chats">
        <div class="flex h-full flex-col bg-gray-50 p-4">
            <div class="border-b bg-white p-4 shadow-sm">
                <h2 class="text-lg font-bold text-gray-800">Чат с {{ props.chat.client.name || 'Аноним' }}</h2>
            </div>

            <!-- Прокручиваемая область сообщений -->
            <div class="flex-1 space-y-3 overflow-y-auto p-4" ref="scrollAreaRef">
                <div v-for="message in messages" :key="message.id" class="animate-slide-in">
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
                                <div class="mt-1 flex items-center gap-1 text-xs opacity-80">
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
                                    v-if="message.sender_type === 'operator' && editingMessageId !== message.id"
                                    @click="startEditing(message)"
                                    class="mt-1 hidden text-xs text-blue-200 transition-colors group-hover:block hover:text-blue-100"
                                >
                                    Редактировать
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="isTyping" class="flex items-center gap-1 text-sm text-gray-500">
                    Клиент печатает
                    <span class="typing-dot">.</span>
                    <span class="typing-dot">.</span>
                    <span class="typing-dot">.</span>
                </div>
            </div>

            <!-- Форма отправки -->
            <div class="border-t bg-white p-4 shadow-sm">
                <form @submit.prevent="sendMessage(messageInput)">
                    <div class="flex space-x-3">
                        <input
                            v-model="messageInput"
                            @input="handleInput"
                            @keydown.enter.prevent="sendMessage(messageInput)"
                            type="text"
                            placeholder="Введите ваше сообщение..."
                            class="flex-grow rounded-full border border-gray-300 px-4 py-2 text-gray-800 shadow-sm transition-all focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                        <button
                            type="submit"
                            class="rounded-full bg-blue-600 p-2 text-white transition-colors hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            <Send class="h-5 w-5" />
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </OperatorLayout>
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

.typing-dot {
    display: inline-block;
    animation: pulse 1s infinite;
}

.typing-dot:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.3;
    }
}
</style>
