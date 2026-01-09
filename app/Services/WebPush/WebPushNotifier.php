<?php

namespace App\Services\WebPush;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushNotifier
{
    public function notifyAll(array $payload, array $options = []): void
    {
        $publicKey = (string) config('services.webpush.public_key', '');
        $privateKey = (string) config('services.webpush.private_key', '');
        $subject = (string) config('services.webpush.subject', '');

        if ($publicKey === '' || $privateKey === '' || $subject === '') {
            return;
        }

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        $webPush = new WebPush($auth, [
            'batchSize' => 200,
            'requestConcurrency' => 50,
        ]);

        $subscriptions = PushSubscription::query()->get([
            'endpoint',
            'public_key',
            'auth_token',
            'content_encoding',
        ]);

        if ($subscriptions->isEmpty()) {
            return;
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            return;
        }

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->public_key,
                'authToken' => $sub->auth_token,
                'contentEncoding' => $sub->content_encoding ?: 'aesgcm',
            ]);

            $webPush->queueNotification($subscription, $jsonPayload, $options);
        }

        try {
            $webPush->flushPooled(function ($report) {
                try {
                    if ($report->isSubscriptionExpired()) {
                        PushSubscription::query()->where('endpoint', $report->getEndpoint())->delete();
                    }
                } catch (\Throwable $e) {
                    Log::warning('[webpush] report handling failed: ' . $e->getMessage());
                }
            });
        } catch (\Throwable $e) {
            Log::warning('[webpush] flush failed: ' . $e->getMessage());
        }
    }

    public function notifyAllExceptUser(int $excludedUserId, array $payload, array $options = []): void
    {
        $publicKey = (string) config('services.webpush.public_key', '');
        $privateKey = (string) config('services.webpush.private_key', '');
        $subject = (string) config('services.webpush.subject', '');

        if ($publicKey === '' || $privateKey === '' || $subject === '') {
            return;
        }

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        $webPush = new WebPush($auth, [
            'batchSize' => 200,
            'requestConcurrency' => 50,
        ]);

        $subscriptions = PushSubscription::query()
            ->where('user_id', '!=', $excludedUserId)
            ->get([
                'endpoint',
                'public_key',
                'auth_token',
                'content_encoding',
            ]);

        if ($subscriptions->isEmpty()) {
            return;
        }

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            return;
        }

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub->endpoint,
                'publicKey' => $sub->public_key,
                'authToken' => $sub->auth_token,
                'contentEncoding' => $sub->content_encoding ?: 'aesgcm',
            ]);

            $webPush->queueNotification($subscription, $jsonPayload, $options);
        }

        try {
            $webPush->flushPooled(function ($report) {
                try {
                    if ($report->isSubscriptionExpired()) {
                        PushSubscription::query()->where('endpoint', $report->getEndpoint())->delete();
                    }
                } catch (\Throwable $e) {
                    Log::warning('[webpush] report handling failed: ' . $e->getMessage());
                }
            });
        } catch (\Throwable $e) {
            Log::warning('[webpush] flush failed: ' . $e->getMessage());
        }
    }
}
