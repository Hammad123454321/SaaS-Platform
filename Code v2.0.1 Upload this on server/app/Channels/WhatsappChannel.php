<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;

class WhatsappChannel
{
    public function send($notifiable, Notification $notification)
    {
        // Check if the notification has a toWhatsApp method
        if (method_exists($notification, 'toWhatsApp')) {
            $message = $notification->toWhatsApp($notifiable);            
        }
    }
}

