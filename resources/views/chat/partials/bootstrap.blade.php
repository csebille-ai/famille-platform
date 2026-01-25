<script>
    window.__CHAT_BOOTSTRAP__ = {
        currentUserId: @json(auth()->id()),
        currentUserName: @json(auth()->user()?->name),
        chatUrl: @json(route('chat.index')),
        pollUrl: @json(route('chat.poll', !empty($conversationWithUserId) ? ['with_user_id' => (int) $conversationWithUserId] : [])),
        recipientsUrl: @json(route('chat.recipients')),
        isAdmin: @json((bool) ($isAdmin ?? false)),
        conversationWithUserId: @json((int) ($conversationWithUserId ?? 0)),
        conversationWithUser: @json($conversationWithUser ?? null),
        quotaUrl: @json(url('/api/uploads/quota')),
        presignUrl: @json(url('/api/uploads/presign')),
        mpInitUrl: @json(url('/api/uploads/multipart/init')),
        mpCompleteUrl: @json(url('/api/uploads/multipart/complete')),
        finalizeUrl: @json(url('/api/uploads/finalize')),
        lastMessageId: @json($lastMessageId ?? 0),
        initialOnline: @json($initialOnline ?? []),
        initialReactionSummaries: @json($reactionSummaries ?? []),
        maxUploadBytes: @json((int) config('uploads.max_upload_bytes')),
        multipartThresholdBytes: @json((int) config('uploads.multipart_threshold_bytes')),
        dashboardUrl: @json(route('dashboard')),
        quickTypeNameCandidates: @json($onlineList->pluck('name')->values()),
    };
</script>
