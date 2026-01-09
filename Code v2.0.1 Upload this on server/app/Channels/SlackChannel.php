<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;

class SlackChannel
{
    public function send($notifiable, Notification $notification)
    {
        // Check if the notification has a toSlack method
        if (method_exists($notification, 'toSlack')) {
            $message = $notification->toSlack($notifiable);
        }
    }
}

