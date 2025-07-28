<script setup lang="ts">

import ClientLayout from '@/layouts/ClientLayout.vue';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
</script>

<template>
    <ClientLayout>
        <div class="fixed right-4 bottom-4 z-50">
            <Card class="flex h-[440px] w-80 flex-col shadow-lg">
                <CardHeader>
                    <div class="flex items-center gap-3">
                        <Avatar>
                            <AvatarImage src="/avatars/operator.png" alt="Operator" />
                            <AvatarFallback>OP</AvatarFallback>
                        </Avatar>
                        <div>
                            <CardTitle class="text-lg">Чат поддержки</CardTitle>
                            <p class="text-sm text-gray-500 italic">Оператор печатает...</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="min-h-0 flex-1 p-0">
                    <ScrollArea ref="scrollAreaRef" class="h-full w-full p-4">
                        <div class="space-y-4">
                            <ChatMessage v-for="msg in messages" :key="msg.id" :message="msg" />
                        </div>
                    </ScrollArea>
                </CardContent>
                <CardFooter class="p-4">
                    <form class="flex w-full items-center space-x-2">
                        <Input v-model="form.content" placeholder="Напишите сообщение..." @input="handleTyping" />
                        <Button type="submit" size="icon">
                            <Send class="h-5 w-5" />
                        </Button>
                    </form>
                </CardFooter>
            </Card>
        </div>
    </ClientLayout>
</template>

<style scoped>

</style>
