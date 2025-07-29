<?php

use App\Http\Controllers\Client\ClientChatController;
use App\Jobs\ProcessChatQueueJob;
use App\Jobs\RetryPendingMessagesJob;
use App\Services\MessageService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/**
 *
 * Тут Хотел показать что можно и Job использовать, но у нас маленький проект, вызов на прямую будет
 *
 * Schedule::job(ProcessChatQueueJob::class)
 * ->everyMinute()
 * ->withoutOverlapping(10)
 * ->description('Ожидающие чаты, назначать им оператора');
 *
 * Schedule::job(RetryPendingMessagesJob::class)
 * ->everyMinute()
 * ->withoutOverlapping(10)
 * ->description('Повторная отправка сообщения');
 *
 * Schedule::call([ClientChatController::class, 'processQueue'])
 * ->everyMinute()
 * ->withoutOverlapping(10)
 * ->sendOutputTo(storage_path('logs/queue.log'))
 * ->description('Process pending chats and assign to available operators');
 *
 * Schedule::call([MessageService::class, 'retryPendingMessages'])
 * ->everyMinute()
 * ->withoutOverlapping(10)
 * ->sendOutputTo(storage_path('logs/retry-messages.log'))
 * ->description('Retry undelivered messages');
 *
 *
 **/


Schedule::job(ProcessChatQueueJob::class)
    ->everyMinute()
    ->withoutOverlapping(10)
    ->description('Process pending chats and assign to available operators');

Schedule::job(RetryPendingMessagesJob::class)
    ->everyMinute()
    ->withoutOverlapping(10)
    ->description('Retry undelivered messages');
