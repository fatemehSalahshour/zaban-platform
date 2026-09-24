<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** هشدار امنیتی برای مدیر — هر نوع برای هر کاربر روزی یک بار. */
class Alerts
{
    public function raise(int $userId, string $kind, ?string $detail = null): void
    {
        $new = DB::table('security_alerts')->insertOrIgnore([
            'user_id' => $userId, 'kind' => $kind, 'detail' => $detail ? mb_substr($detail, 0, 255) : null,
            'day' => now()->toDateString(), 'created_at' => now(),
        ]);
        if ($new) Log::warning("[Security] $kind", ['user' => $userId, 'detail' => $detail]);
    }
}
