<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPush\WebPushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTestController extends Controller
{
    public function __invoke(Request $request, WebPushNotifier $notifier): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $subscriptionCount = (int) PushSubscription::query()
            ->where('user_id', (int) $user->id)
            ->count();

        if ($subscriptionCount <= 0) {
            return response()->json([
                'ok' => false,
                'message' => 'Aucun abonnement push enregistré pour ce compte sur cet appareil.',
                'subscription_count' => $subscriptionCount,
            ]);
        }

        $publicKey = (string) config('services.webpush.public_key', '');
        $privateKey = (string) config('services.webpush.private_key', '');
        $subject = (string) config('services.webpush.subject', '');
        $configured = $publicKey !== '' && $privateKey !== '' && $subject !== '';

        if (!$configured) {
            return response()->json([
                'ok' => false,
                'message' => 'Push non configuré côté serveur (VAPID).',
                'subscription_count' => $subscriptionCount,
                'configured' => false,
            ]);
        }

        $payload = [
            'title' => 'Famille — Test',
            'body' => 'Notification de test (si tu lis ça, ça marche).',
            'url' => route('chat.index'),
        ];

        try {
            $notifier->notifyUsers([(int) $user->id], $payload);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Envoi impossible: ' . $e->getMessage(),
                'subscription_count' => $subscriptionCount,
                'configured' => true,
            ]);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Test envoyé. Si tu ne reçois rien, regarde les permissions et l’installation PWA.',
            'subscription_count' => $subscriptionCount,
            'configured' => true,
        ]);
    }
}
