<?php

use App\Jobs\ProcessChatQueueJob;
use App\Jobs\RetryPendingMessagesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    Schedule::job(ProcessChatQueueJob::class)
        ->everyFiveSeconds()
        ->withoutOverlapping(10)
        ->description('Обработка ожидающих чатов');

    Schedule::job(RetryPendingMessagesJob::class)
        ->everyTenSeconds()
        ->withoutOverlapping(10)
        ->description('Повтор недоставленных сообщений');
})->name('schedule:run')->withoutOverlapping();
