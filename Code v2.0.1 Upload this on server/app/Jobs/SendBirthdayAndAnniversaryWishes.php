<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Client;
use Illuminate\Support\Facades\Mail;

class SendBirthdayAndAnniversaryWishes extends Job
{
    public function handle()
    {
        // Get today's date
        $today = now()->toDateString();

        // Check for users with birthdays today
        $users = User::whereDate('dob', $today)->get();
        foreach ($users as $user) {
            Mail::to($user->email)->send(new BirthdayWishMail($user));
        }

        // Check for clients with work anniversaries today
        $clients = Client::whereDate('doj', $today)->get();
        foreach ($clients as $client) {
            Mail::to($client->email)->send(new AnniversaryWishMail($client));
        }
    }
}
