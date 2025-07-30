<script setup lang="ts">
import ChatMessage from '@/components/ChatMessage.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import ClientLayout from '@/layouts/ClientLayout.vue';
import axios from '@/lib/axios';
import type { Chat, Message } from '@/types';
import { Check, Send, X } from 'lucide-vue-next';
import { v4 as uuidv4 } from 'uuid';
import { nextTick, onMounted, onUnmounted, reactive, ref, watch } from 'vue';

const props = defineProps<{ chat: Chat }>();
const messages = ref<Message[]>(props.chat.messages || []);
const messageInput = ref('');
const lastEventId = ref(0);
const polling = ref(true);
const isTyping = ref(false);
const typingTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const isPending = ref(props.chat.status === 'pending');
const isClosed = ref(props.chat.status === 'closed');
const newName = ref('');
const isPopoverOpen = ref(false);
const chat = reactive(props.chat);
const queuePosition = ref<number | null>(null);

const updateName = async () => {
    const trimmedName = newName.value.trim();
    if (!trimmedName || trimmedName.length < 2) {
        console.log('Имя должно содержать минимум 2 символов');
        return;
    }
    try {
        await axios.post(route('client.chat.update-name', props.chat.id), {
            name: trimmedName,
        });
        chat.client.name = trimmedName;
        isPopoverOpen.value = false;
        newName.value = '';
    } catch (error: any) {
        console.error('Ошибка обновления имени:', error);
    }
};

const closeChat = async () => {
    try {
        await axios.post(route('client.chat.close', props.chat.id));
        window.location.href = route('home');
    } catch (error: any) {
        console.error('Ошибка закрытия чата:', error);
    }
};

const sendMessage = async (content: string) => {
    if (!content.trim() || isPending.value || isClosed.value) return;
    const messageUuid = uuidv4();

    try {
        const response = await axios.post(route('client.chat.send', props.chat.id), {
            content,
            uuid: messageUuid,
        });
        messages.value.push(response.data.message);
        messageInput.value = '';
        sendTypingEvent(false);
    } catch (error: any) {
        console.error('Ошибка отправки сообщения:', error);
    }
};

const editMessage = async (messageId: number, content: string) => {
    if (isPending.value || isClosed.value) return;
    try {
        const response = await axios.post(
            route('client.chat.edit', {
                chat_id: props.chat.id,
                message_id: messageId,
            }),
            { content },
        );
        const updatedMessage = response.data.message;
        const index = messages.value.findIndex((msg) => msg.id === messageId);
        if (index !== -1) {
            messages.value[index] = updatedMessage;
        }
    } catch (error: any) {
        console.error('Ошибка редактирования сообщения:', error);
    }
};

const sendTypingEvent = async (typing: boolean) => {
    if (isPending.value || isClosed.value) return;
    try {
        await axios.post(route('client.chat.typing', props.chat.id), { typing });
    } catch (error) {
        console.error('Ошибка отправки события печатает:', error);
    }
};

const handleInput = () => {
    if (typingTimeout.value) clearTimeout(typingTimeout.value);
    if (isPending.value || isClosed.value) return;

    if (messageInput.value.trim()) {
        sendTypingEvent(true);
        typingTimeout.value = setTimeout(() => sendTypingEvent(false), 3000);
    } else {
        sendTypingEvent(false);
    }
};

const markMessagesAsRead = async () => {
    if (isPending.value || isClosed.value) return;
    const unreadMessages = messages.value.filter((msg) => msg.sender_type === 'operator' && msg.status === 'delivered').map((msg) => msg.id);
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
        const { messages: newMessages, typing_events, last_event_id, chat_status, queue_position, events } = response.data;
        if (newMessages.length) {
            messages.value = newMessages;
            markMessagesAsRead();
        }
        if (typing_events.length) {
            const latestTypingEvent = typing_events[typing_events.length - 1];
            isTyping.value = latestTypingEvent.event_type === 'typing_start';
        }
        isPending.value = chat_status === 'pending';
        isClosed.value = chat_status === 'closed';
        queuePosition.value = queue_position;
        const chatAssigned = events?.some((event: any) => event.event_type === 'chat_assigned');
        if (chatAssigned && isPending.value) {
            isPending.value = false;
            console.log('Оператор подключился к чату!');
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

const scrollAreaRef = ref<InstanceType<typeof ScrollArea> | null>(null);
watch(
    messages,
    async () => {
        await nextTick();
        if (scrollAreaRef.value) {
            scrollAreaRef.value.$el.scrollTo({
                top: scrollAreaRef.value.$el.scrollHeight,
                behavior: 'smooth',
            });
        }
        markMessagesAsRead();
    },
    { deep: true },
);

onMounted(() => {
    polling.value = true;
    pollMessages();
});
onUnmounted(() => {
    polling.value = false;
    if (typingTimeout.value) clearTimeout(typingTimeout.value);
});
</script>

<template>
    <ClientLayout>
        <div class="fixed right-4 bottom-4 z-50">
            <Card class="flex h-[440px] w-80 flex-col rounded-xl shadow-lg">
                <CardHeader class="rounded-t-xl bg-white shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <Avatar>
                                <AvatarImage src="/avatars/operator.png" alt="Operator" />
                                <AvatarFallback>OP</AvatarFallback>
                            </Avatar>
                            <div>
                                <CardTitle class="text-lg text-gray-800">Чат поддержки</CardTitle>
                                <p v-if="isPending" class="text-sm text-gray-500 italic">
                                    Ожидание оператора...
                                    <span v-if="queuePosition !== null">Позиция в очереди: {{ queuePosition }}</span>
                                </p>
                                <p v-else-if="isClosed" class="text-sm text-gray-500 italic">Чат завершён</p>
                                <p v-else class="text-sm text-gray-500 italic">
                                    Чем вам помочь, {{ chat.client?.name }}?
                                    <Popover v-model:open="isPopoverOpen">
                                        <PopoverTrigger asChild>
                                            <Button variant="link" class="h-auto p-0 text-sm text-blue-600"> Изменить имя </Button>
                                        </PopoverTrigger>
                                        <PopoverContent>
                                            <div class="mt-1 flex items-center gap-2">
                                                <Input
                                                    v-model="newName"
                                                    placeholder="Введите новое имя"
                                                    class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                                    @keydown.enter.prevent="updateName"
                                                    aria-label="Новое имя клиента"
                                                />
                                                <Button size="sm" class="rounded-md bg-blue-600 hover:bg-blue-700" @click="updateName">
                                                    <Check />
                                                </Button>
                                                <Button size="sm" variant="destructive" class="rounded-md" @click="isPopoverOpen = false">
                                                    <X />
                                                </Button>
                                            </div>
                                        </PopoverContent>
                                    </Popover>
                                </p>
                            </div>
                        </div>
                        <Button
                            v-if="!isPending && !isClosed"
                            variant="destructive"
                            size="icon"
                            class="rounded-full"
                            @click="closeChat"
                            title="Закрыть чат"
                        >
                            <X class="h-5 w-5" />
                        </Button>
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
                            <div v-if="isTyping && !isPending && !isClosed" class="flex items-center gap-1 text-sm text-gray-500 italic">
                                Оператор печатает
                                <span class="typing-dot">.</span>
                                <span class="typing-dot">.</span>
                                <span class="typing-dot">.</span>
                            </div>
                        </div>
                    </ScrollArea>
                </CardContent>
                <CardFooter class="flex flex-col rounded-b-xl bg-white p-4 shadow-sm">
                    <form v-if="!isPending && !isClosed" class="flex w-full items-center space-x-2" @submit.prevent="sendMessage(messageInput)">
                        <Input
                            v-model="messageInput"
                            placeholder="Напишите сообщение..."
                            @input="handleInput"
                            class="rounded-full border-gray-300 shadow-sm transition-all focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                        <Button
                            type="submit"
                            size="icon"
                            class="rounded-full bg-blue-600 transition-colors hover:bg-blue-700 focus:ring-2 focus:ring-blue-500"
                        >
                            <Send class="h-5 w-5" />
                        </Button>
                    </form>
                    <p v-else-if="isPending" class="text-sm text-gray-500 italic">
                        Ожидание оператора...
                        <span v-if="queuePosition !== null">Позиция в очереди: {{ queuePosition }}</span>
                    </p>
                    <p v-else class="text-sm text-gray-500 italic">Чат завершён</p>
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
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.3;
    }
}
</style>
