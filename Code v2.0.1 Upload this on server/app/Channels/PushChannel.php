<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;

class PushChannel
{
    public function send($notifiable, Notification $notification)
    {
        // Check if the notification has a toPush method
        if (method_exists($notification, 'toPush')) {
            $message = $notification->toPush($notifiable);
        }
    }
}

