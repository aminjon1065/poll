<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppLogo from '@/components/AppLogo.vue';
import AppShell from '@/components/AppShell.vue';
import { cn } from '@/lib/utils';
import type { Chat } from '@/types';
import { Link } from '@inertiajs/vue3';
import { formatDistanceToNow } from 'date-fns';
import { ru } from 'date-fns/locale';
import 'vue-toastification/dist/index.css';

defineProps<{
    chats: Chat[];
}>();
</script>
<template>
    <AppShell variant="sidebar">
        <!--        <AppSidebar />-->
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <div class="flex w-full">
                <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                    <div class="w-96 flex-col md:flex">
                        <AppLogo />
                        <TransitionGroup name="list" appear>
                            <Link v-for="item of chats" :key="item.id" :href="route('operator.chat.show', item.id)">
                                <div :class="cn('mb-5 flex w-full cursor-pointer flex-col items-start gap-2 rounded-lg border p-4 hover:bg-muted')">
                                    <div class="flex w-full flex-col gap-1">
                                        <div class="flex items-center">
                                            <div class="flex items-center gap-2">
                                                <div class="text-muted-foreground">
                                                    {{ item.status }}
                                                </div>
                                            </div>
                                            <div :class="cn('ml-auto text-xs', 1 === item.id ? 'text-foreground' : 'text-muted-foreground')">
                                                {{
                                                    formatDistanceToNow(new Date(item.created_at), {
                                                        addSuffix: true,
                                                        locale: ru,
                                                    })
                                                }}
                                            </div>
                                        </div>
                                        <div class="text-xs font-medium">
                                            {{ item.client.name }}
                                        </div>
                                    </div>
                                </div>
                            </Link>
                        </TransitionGroup>
                    </div>
                </div>
                <div class="w-9/12 rounded border p-10">
                    <slot />
                </div>
            </div>
        </AppContent>
    </AppShell>
</template>

<style scoped></style>
