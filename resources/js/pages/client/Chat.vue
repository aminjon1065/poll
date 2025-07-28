<script setup lang="ts">
import ChatMessage from '@/components/ChatMessage.vue';
import { Avatar, AvatarImage, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import ClientLayout from '@/layouts/ClientLayout.vue';
import axios from '@/lib/axios';
import { Send } from 'lucide-vue-next';
import { v4 as uuidv4 } from 'uuid';
import { onMounted, onUnmounted, ref, watch, nextTick } from 'vue';
import type { Chat, Message } from '@/types';

const props = defineProps<{ chat: Chat }>();
const messages = ref<Message[]>(props.chat.messages || []);
const messageInput = ref('');
const lastEventId = ref(0);
const polling = ref(true);
const isTyping = ref(false);
const typingTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const pendingMessages = ref<{ [key: string]: { content: string; timeout: ReturnType<typeof setTimeout> } }>({});

const sendMessage = async (content: string, uuid: string | null = null) => {
    if (!messageInput.value.trim()) return;
    const messageUuid = uuid ?? uuidv4();

    try {
        const response = await axios.post(route('client.chat.send', props.chat.id), {
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

const retryMessage = async (uuid: string, content: string) => {
    const message = messages.value.find(msg => msg.uuid === uuid);
    if (message && message.status === 'sent') {
        console.log(`Повторная отправка сообщения ${uuid}`);
        await sendMessage(content, uuid);
    }
};

const editMessage = async (messageId: number, content: string) => {
    try {
        const response = await axios.post(
            route('client.chat.edit', { chat_id: props.chat.id, message_id: messageId }),
            { content }
        );
        const updatedMessage = response.data.message;
        const index = messages.value.findIndex(msg => msg.id === messageId);
        if (index !== -1) {
            messages.value[index] = updatedMessage;
        }
    } catch (error: any) {
        console.error('Ошибка редактирования сообщения:', error);
        alert(error.response?.data?.error || 'Не удалось отредактировать сообщение');
    }
};

const sendTypingEvent = async (typing: boolean) => {
    try {
        await axios.post(route('client.chat.typing', props.chat.id), { typing });
    } catch (error) {
        console.error('Ошибка отправки события печатает:', error);
    }
};

const handleInput = () => {
    if (typingTimeout.value) clearTimeout(typingTimeout.value);

    if (messageInput.value.trim()) {
        sendTypingEvent(true);
        typingTimeout.value = setTimeout(() => sendTypingEvent(false), 3000);
    } else {
        sendTypingEvent(false);
    }
};

const markMessagesAsRead = async () => {
    const unreadMessages = messages.value
        .filter(msg => msg.sender_type === 'operator' && msg.status === 'delivered')
        .map(msg => msg.id);
    if (unreadMessages.length) {
        try {
            await axios.post(route('client.chat.read', props.chat.id), {
                message_ids: unreadMessages,
            });
        } catch (error) {
            console.error('Ошибка отметки сообщений как прочитанных:', error);
        }
    }
};

const pollMessages = async () => {
    if (!polling.value) return;

    try {
        const response = await axios.get(route('client.chat.poll', props.chat.id), {
            params: { last_event_id: lastEventId.value },
        });
        const { messages: newMessages, typing_events, last_event_id } = response.data;
        if (newMessages.length) {
            messages.value = newMessages;
            newMessages.forEach((msg: Message) => {
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
        console.error('Ошибка longpoll:', error);
    } finally {
        if (polling.value) {
            setTimeout(pollMessages, 100);
        }
    }
};

const scrollAreaRef = ref<InstanceType<typeof ScrollArea> | null>(null);
watch(messages, async () => {
    await nextTick();
    if (scrollAreaRef.value) {
        scrollAreaRef.value.$el.scrollTo({
            top: scrollAreaRef.value.$el.scrollHeight,
            behavior: 'smooth',
        });
    }
    markMessagesAsRead();
}, { deep: true });

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
    <ClientLayout>
        <div class="fixed right-4 bottom-4 z-50">
            <Card class="flex h-[440px] w-80 flex-col shadow-lg rounded-xl">
                <CardHeader class="bg-white shadow-sm rounded-t-xl">
                    <div class="flex items-center gap-3">
                        <Avatar>
                            <AvatarImage src="/avatars/operator.png" alt="Operator" />
                            <AvatarFallback>OP</AvatarFallback>
                        </Avatar>
                        <div>
                            <CardTitle class="text-lg text-gray-800">Чат поддержки</CardTitle>
                            <p class="text-sm text-gray-500 italic">Чем вам помочь?</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="min-h-0 flex-1 p-0">
                    <ScrollArea ref="scrollAreaRef" class="h-full w-full p-4">
                        <div class="space-y-3">
                            <ChatMessage
                                v-for="msg in messages"
                                :key="msg.id"
                                :message="msg"
                                :is-own-message="msg.sender_type === 'client'"
                                @edit="editMessage"
                                class="animate-slide-in"
                            />
                            <div v-if="isTyping" class="text-sm text-gray-500 italic flex items-center gap-1">
                                Оператор печатает
                                <span class="typing-dot">.</span>
                                <span class="typing-dot">.</span>
                                <span class="typing-dot">.</span>
                            </div>
                        </div>
                    </ScrollArea>
                </CardContent>
                <CardFooter class="flex flex-col p-4 bg-white shadow-sm rounded-b-xl">
                    <form class="flex w-full items-center space-x-2" @submit.prevent="sendMessage(messageInput)">
                        <Input
                            v-model="messageInput"
                            placeholder="Напишите сообщение..."
                            @input="handleInput"
                            class="rounded-full border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none transition-all"
                        />
                        <Button type="submit" size="icon" class="rounded-full bg-blue-600 hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 transition-colors">
                            <Send class="h-5 w-5" />
                        </Button>
                    </form>
                </CardFooter>
            </Card>
        </div>
    </ClientLayout>
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
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.3;
    }
}
</style>
