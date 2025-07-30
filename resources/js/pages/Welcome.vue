<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Head, Link } from '@inertiajs/vue3';
import { Headset, PersonStanding } from 'lucide-vue-next';
</script>

<template>
    <Head title="Welcome" />
    <div
        class="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]">
        <div
            class="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
            <main
                class="flex w-full flex-col-reverse justify-center overflow-hidden rounded-lg lg:max-w-4xl lg:flex-row">
                <div
                    class="flex space-x-4 rounded-lg bg-white p-6 pb-12 text-center text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-lg lg:p-20 dark:bg-[#161615] dark:text-[#EDEDEC] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]"
                >
                    <Link :href="route('dashboard')">
                        <Button variant="ghost">
                            <Headset />
                            Я Оператор
                        </Button>
                    </Link>
                    <Separator orientation="vertical" />
                    <Link :href="route('chat.start')" method="post">
                        <Button variant="ghost">
                            <PersonStanding />
                            Я Клиент
                        </Button>
                    </Link>
                </div>
            </main>
        </div>
        <div class="hidden h-14.5 lg:block"></div>
    </div>
</template>
