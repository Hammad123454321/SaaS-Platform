<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function send($notifiable, Notification $notification)
    {
        // Check if the notification has a toSms method
        if (method_exists($notification, 'toSms')) {
            $message = $notification->toSms($notifiable);
        }
    }
}

