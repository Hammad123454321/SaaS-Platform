<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Client;
use App\Models\Notification as ModelNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Notifications\BirthdayWishNotification;
use App\Notifications\WorkAnniversaryWishNotification;

class SendWishes extends Command
{
    protected $signature = 'send:wishes';
    protected $description = 'Send birthday and work anniversary wishes to users and clients';

    public function handle()
    {
        $today = Carbon::now()->format('m-d');
        $this->info("Running send:wishes for date: $today");
        Log::info("SendWishes command started for date: $today");

        $globalSettings = ['email', 'sms', 'push', 'whatsapp', 'system', 'slack'];
        $notificationTypes = ['birthday_wish', 'work_anniversary_wish'];

        $globalNotifications = [];
        foreach ($notificationTypes as $type) {
            foreach ($globalSettings as $channel) {
                $template = getNotificationTemplate($type, $channel);
                $globalNotifications[$type][$channel] = !$template || $template->status === 1;
                Log::debug("Template check: {$type} / {$channel} => " . json_encode($globalNotifications[$type][$channel]));
            }
        }

        $currentYear = today()->year;

        // === USERS ===
        $birthdayUsers = User::where('status', 1)
            ->whereRaw("DATE_FORMAT(dob, '%m-%d') = ?", [$today])
            ->whereRaw("YEAR(dob) <= ?", [$currentYear]) // FIXED
            ->get();
        $this->info("Found {$birthdayUsers->count()} birthday users.");
        Log::info("Birthday Users: " . $birthdayUsers->pluck('id')->join(', '));

        $workAnniversaryUsers = User::where('status', 1)
            ->whereRaw("DATE_FORMAT(doj, '%m-%d') = ?", [$today])
            ->whereRaw("YEAR(doj) <= ?", [$currentYear]) // FIXED
            ->get();
        $this->info("Found {$workAnniversaryUsers->count()} work anniversary users.");
        Log::info("Work Anniversary Users: " . $workAnniversaryUsers->pluck('id')->join(', '));

        // === CLIENTS ===
        $birthdayClients = Client::where('status', 1)
            ->whereRaw("DATE_FORMAT(dob, '%m-%d') = ?", [$today])
            ->whereRaw("YEAR(dob) <= ?", [$currentYear]) // FIXED
            ->get();
        $this->info("Found {$birthdayClients->count()} birthday clients.");
        Log::info("Birthday Clients: " . $birthdayClients->pluck('id')->join(', '));

        $workAnniversaryClients = Client::where('status', 1)
            ->whereRaw("DATE_FORMAT(doj, '%m-%d') = ?", [$today])
            ->whereRaw("YEAR(doj) <= ?", [$currentYear]) // FIXED
            ->get();
        $this->info("Found {$workAnniversaryClients->count()} work anniversary clients.");
        Log::info("Work Anniversary Clients: " . $workAnniversaryClients->pluck('id')->join(', '));

        $this->sendNotifications($birthdayUsers, 'birthday_wish', $globalNotifications);
        $this->sendNotifications($workAnniversaryUsers, 'work_anniversary_wish', $globalNotifications);
        $this->sendNotifications($birthdayClients, 'birthday_wish', $globalNotifications);
        $this->sendNotifications($workAnniversaryClients, 'work_anniversary_wish', $globalNotifications);

        $this->info('✅ Birthday and work anniversary wishes sent successfully.');
        Log::info("SendWishes command completed.");
    }

    private function sendNotifications($recipients, $type, $globalNotifications)
    {
        $currentYear = today()->year;
        foreach ($recipients as $recipient) {
            $recipientId = ($recipient instanceof User) ? 'u_' . $recipient->id : 'c_' . $recipient->id;
            $this->info("Sending {$type} for recipient ID: {$recipientId}");
            Log::debug("Sending {$type} to {$recipient->first_name} [{$recipientId}]");

            $enabledNotifications = getUserPreferences('notification_preference', 'enabled_notifications', $recipientId);
            Log::debug("Enabled notifications for {$recipientId}: " . json_encode($enabledNotifications));

            $notificationData = [
                'first_name' => $recipient->first_name,
                'last_name' => $recipient->last_name,
            ];

            if ($type === 'birthday_wish') {
                $years = $currentYear - Carbon::parse($recipient->dob)->year;
                $notificationData['birthday_count'] = $years;
                $notificationData['ordinal_suffix'] = getOrdinalSuffix($years);
                $notificationData['type'] = 'birthday_wish';
            } else {
                $years = $currentYear - Carbon::parse($recipient->doj)->year;
                $notificationData['work_anniversary_count'] = $years;
                $notificationData['ordinal_suffix'] = getOrdinalSuffix($years);
                $notificationData['type'] = 'work_anniversary_wish';
            }

            $isSystemEnabled = $this->isNotificationEnabled($enabledNotifications, $type, 'system');
            $isPushEnabled = $this->isNotificationEnabled($enabledNotifications, $type, 'push');

            if ($globalNotifications[$type]['system'] || $globalNotifications[$type]['push']) {
                $title = $message = '';

                if ($globalNotifications[$type]['system'] && $isSystemEnabled) {
                    $title = getTitle($notificationData, $recipient, 'system');
                    $message = get_message($notificationData, $recipient, 'system');
                }

                if ($globalNotifications[$type]['push'] && $isPushEnabled) {
                    $pushTitle = getTitle($notificationData, $recipient, 'push');
                    $pushMessage = get_message($notificationData, $recipient, 'push');

                    if (empty($title) && empty($message)) {
                        $title = $pushTitle;
                        $message = $pushMessage;
                    }
                }
                $workspace = $recipient->workspaces->where('is_primary', '1')->first()
                    ?? $recipient->workspaces->first();

                if (!empty($title) && !empty($message)) {
                    $notification = ModelNotification::create([
                        'workspace_id' => $workspace->id,
                        'type' => $notificationData['type'],
                        'title' => $title,
                        'message' => $message,
                    ]);

                    $recipient->notifications()->attach($notification->id, [
                        'is_system' => $isSystemEnabled ? 1 : 0,
                        'is_push' => $isPushEnabled ? 1 : 0,
                    ]);

                    Log::info("System/push notification created for {$recipientId}: {$title}");
                } else {
                    Log::warning("Skipped system/push for {$recipientId} due to empty title/message");
                }
            }

            foreach (['email', 'sms', 'push', 'whatsapp', 'slack'] as $channel) {
                if ($globalNotifications[$type][$channel] && $this->isNotificationEnabled($enabledNotifications, $type, $channel)) {
                    try {
                        $notificationClass = $type === 'birthday_wish'
                            ? BirthdayWishNotification::class
                            : WorkAnniversaryWishNotification::class;

                        Notification::send($recipient, new $notificationClass([
                            'channel' => match ($channel) {
                                'email' => 'mail',
                                'sms' => \App\Channels\SmsChannel::class,
                                'whatsapp' => \App\Channels\WhatsappChannel::class,
                                'push' => \App\Channels\PushChannel::class,
                                'slack' => \App\Channels\SlackChannel::class,
                                default => $channel,
                            },
                            'notification_data' => $notificationData,
                        ]));

                        Log::info("Notification sent to {$recipientId} via {$channel}");
                    } catch (\Exception $e) {
                        Log::error("Error sending {$channel} notification to {$recipientId}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    private function isNotificationEnabled($enabledNotifications, $type, $channel)
    {
        return (
            (is_array($enabledNotifications) && empty($enabledNotifications)) ||
            (is_array($enabledNotifications) && in_array("{$channel}_{$type}", $enabledNotifications))
        );
    }
}
