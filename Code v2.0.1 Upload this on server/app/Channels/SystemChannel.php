<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;

class SystemChannel
{
    public function send($notifiable, Notification $notification)
    {
        // Check if the notification has a toSystem method
        if (method_exists($notification, 'toSystem')) {
            $message = $notification->toSystem($notifiable);
        }
    }
}

