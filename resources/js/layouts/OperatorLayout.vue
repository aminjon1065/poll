<!--Это Layout для операторов, тут списки клиентов присвоенноые оператору обнояляються в реальном времени(заддержка 10-12 секунд хотя максимальна 10секунд задано)-->
<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppLogo from '@/components/AppLogo.vue';
import AppShell from '@/components/AppShell.vue';
import axios from '@/lib/axios';
import { cn } from '@/lib/utils';
import type { Chat } from '@/types';
import { Link } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { ru } from 'date-fns/locale';
import { onMounted, onUnmounted, ref } from 'vue';
import { useToast } from 'vue-toastification';
import 'vue-toastification/dist/index.css';

const toast = useToast();
const props = defineProps<{
    chats: Chat[];
}>();

const chats = ref<Chat[]>(props.chats);
const lastEventId = ref(0);
const polling = ref(true);
const isPolling = ref(false);
const isTabActive = ref(true);
const pollInterval = ref(3000);

const handleVisibilityChange = () => {
    isTabActive.value = !document.hidden;
    pollInterval.value = isTabActive.value ? 3000 : 5000;
};

const pollChats = async () => {
    if (!polling.value || isPolling.value || !isTabActive.value) return;

    isPolling.value = true;
    try {
        const response = await axios.get(route('operator.poll.chats'), {
            params: { last_event_id: lastEventId.value },
            timeout: 10000,
        });
        const { chats: newChats, last_event_id } = response.data;

        if (newChats.length > chats.value.length) {
            toast.success('Назначен новый чат!');
            pollInterval.value = 2000;
        } else {
            pollInterval.value = Math.min(pollInterval.value + 500, 5000);
        }

        chats.value = newChats;
        lastEventId.value = last_event_id;
    } catch (error: any) {
        if (error.code === 'ECONNABORTED') {
            console.warn('Запрос pollChats');
        }
        pollInterval.value = Math.min(pollInterval.value + 1000, 10000);
    } finally {
        isPolling.value = false;
        if (polling.value) {
            setTimeout(pollChats, pollInterval.value);
        }
    }
};

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    polling.value = true;
    pollChats();
});

onUnmounted(() => {
    polling.value = false;
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <div class="flex w-full">
                <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div class="mx-auto w-96 flex-col md:flex">
                        <div class="mx-auto">
                            <AppLogo />
                        </div>
                        <Link v-for="item of chats" :key="item.id" :href="route('operator.chat.show', item.id)">
                            <div :class="cn('mb-5 flex w-full cursor-pointer flex-col items-start gap-2 rounded-lg border p-4 hover:bg-muted')">
                                <div class="flex w-full flex-col gap-1">
                                    <div class="flex items-center">
                                        <div class="flex items-center gap-2">
                                            <div class="text-muted-foreground">
                                                {{ item.status }}
                                            </div>
                                        </div>
                                        <div :class="cn('ml-auto text-xs', item.id === 1 ? 'text-foreground' : 'text-muted-foreground')">
                                            {{
                                                formatDistanceToNow(new Date(item.created_at), {
                                                    addSuffix: true,
                                                    locale: ru,
                                                })
                                            }}
                                        </div>
                                    </div>
                                    <div class="text-xs font-medium">
                                        {{ item.client?.name || 'Клиент' }}
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
                <div class="w-9/12 rounded border">
                    <slot />
                </div>
            </div>
        </AppContent>
    </AppShell>
</template>

<style scoped></style>
