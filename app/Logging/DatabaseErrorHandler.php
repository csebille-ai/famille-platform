<?php

namespace App\Logging;

use App\Models\AppError;
use Carbon\CarbonImmutable;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class DatabaseErrorHandler extends AbstractProcessingHandler
{
    public function __construct(int|string|Level $level = Level::Error, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            if (!config('ops.errors.log_to_db', true)) {
                return;
            }

            $request = null;
            try {
                $request = request();
            } catch (\Throwable) {
                $request = null;
            }

            $route = null;
            $userId = null;

            if ($request) {
                try {
                    $route = '/' . ltrim((string) $request->path(), '/');
                    $userId = $request->user()?->id;
                } catch (\Throwable) {
                    // ignore
                }
            }

            $context = $record->context;
            $extra = $record->extra;

            $stack = null;
            $ex = $context['exception'] ?? null;
            if ($ex instanceof \Throwable) {
                $stack = (string) $ex;
            }

            $message = trim((string) $record->message);
            if ($message === '') {
                $message = $record->level->getName();
            }

            AppError::create([
                'created_at' => CarbonImmutable::instance($record->datetime),
                'level' => $record->level->getName(),
                'message' => mb_substr($message, 0, 1024),
                'route' => $route !== null ? mb_substr($route, 0, 255) : null,
                'user_id' => $userId,
                'stacktrace' => $stack,
                'context' => [
                    'context' => $this->safeJson($context),
                    'extra' => $this->safeJson($extra),
                ],
            ]);
        } catch (\Throwable) {
            // Absolutely never throw from logging.
        }
    }

    private function safeJson(array $value): array
    {
        $sanitized = [];
        foreach ($value as $k => $v) {
            if ($v instanceof \Throwable) {
                $sanitized[$k] = [
                    'class' => $v::class,
                    'message' => $v->getMessage(),
                ];
            } elseif (is_scalar($v) || $v === null) {
                $sanitized[$k] = $v;
            } elseif (is_array($v)) {
                $sanitized[$k] = $this->safeJson($v);
            } elseif (is_object($v)) {
                $sanitized[$k] = ['class' => $v::class];
            } else {
                $sanitized[$k] = (string) gettype($v);
            }
        }

        return $sanitized;
    }
}
