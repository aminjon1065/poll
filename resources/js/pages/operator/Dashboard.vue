<script setup lang="ts">
import { ScrollArea } from '@/components/ui/scroll-area';
import OperatorLayout from '@/layouts/OperatorLayout.vue';
import type { Chat } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';
import { formatDistanceToNow } from 'date-fns';
import { Badge } from '@/components/ui/badge';
import { ru } from 'date-fns/locale'
const props = defineProps<{
    chats: Chat;
}>();

const page = usePage();
</script>

<template>
    <Head title="Dashboard" />
    <OperatorLayout>
        <div class="w-96 flex-col md:flex">
            <ScrollArea class="flex h-screen">
                <TransitionGroup name="list" appear>
                    <button
                        v-for="item of chats"
                        :key="item.id"
                        :class="cn('flex w-full flex-col items-start gap-2 mb-5 cursor-pointer p-4 rounded-lg border', item.id === page.url && 'bg-muted')"
                    >
                        <div class="flex w-full flex-col gap-1">
                            <div class="flex items-center">
                                <div class="flex items-center gap-2">
                                    <div class="font-semibold">
                                        {{ item.s }}
                                    </div>
                                    <span v-if="!item.read" class="flex h-2 w-2 rounded-full bg-blue-600" />
                                </div>
                                <div :class="cn('ml-auto text-xs', 1 === item.id ? 'text-foreground' : 'text-muted-foreground')">
                                    {{ formatDistanceToNow(new Date(item.created_at), { addSuffix: true, locale:ru }) }}
                                </div>
                            </div>

                            <div class="text-xs font-medium">
                                {{ item.status }}
                            </div>
                        </div>
                        <div class="line-clamp-2 text-xs text-muted-foreground">Lorem ipsum.</div>
                        <div class="flex items-center gap-2">
                            <Badge v-for="label of item.labels" :key="label">
                                {{ label }}
                            </Badge>
                        </div>
                    </button>
                </TransitionGroup>
            </ScrollArea>
        </div>
    </OperatorLayout>
</template>

<style scoped></style>
