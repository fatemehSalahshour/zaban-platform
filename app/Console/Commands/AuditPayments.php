<?php

namespace App\Console\Commands;

use App\Support\Roles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan zaban:audit-payments — حسابرسی پرداخت‌ها و دسترسی‌ها (کیت امنیت، بخش ۲-۲۱).
 *
 * فقط گزارش می‌دهد؛ هیچ چیزی را تغییر نمی‌دهد. تصمیم لغو یا برگشت وجه با مدیر است.
 *
 *   ۱) دسترسی «خرید» بدون سفارش پرداخت‌شده          ← فعال‌سازی رایگانِ بی‌تراکنش
 *   ۲) سفارش پرداخت‌شده‌ی درگاهی بدون کد پیگیری       ← پرداخت ثبت‌شده ولی بی‌مدرک
 *   ۳) مبلغ ریالی ناسازگار با مبلغ تومانی سفارش
 *   ۴) پرداخت دوباره برای همان رشته                  ← کاندید برگشت وجه
 *   ۵) دسترسی دستیِ («staff») دانشجوها              ← فهرست برای بازبینی، با یادداشت اعطاکننده
 *   ۶) سفارش گیرکرده‌ی قدیمی‌تر از ۷ روز             ← استعلام خودکار دیگر جواب نمی‌دهد
 */
class AuditPayments extends Command
{
    protected $signature = 'zaban:audit-payments';
    protected $description = 'حسابرسی پرداخت‌ها و دسترسی‌ها (فقط گزارش)';

    private int $issues = 0;

    public function handle(): int
    {
        $this->section('۱) دسترسی «خرید» بدون سفارش پرداخت‌شده');
        $rows = DB::table('zaban_entitlements as e')
            ->leftJoin('zaban_orders as o', 'o.id', '=', 'e.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.source', 'purchase')
            ->where(fn ($q) => $q->whereNull('o.id')->orWhere('o.status', '!=', 'paid'))
            ->get(['e.user_id', 'u.mobile', 'e.exam', 'e.order_id', 'o.status', 'e.revoked_at']);
        $this->report($rows, ['کاربر', 'موبایل', 'رشته', 'سفارش', 'وضعیت سفارش', 'لغوشده'],
            fn ($r) => [$r->user_id, $r->mobile, $r->exam, $r->order_id ?: '—', $r->status ?: 'ندارد', $r->revoked_at ?: '']);

        $this->section('۲) پرداخت درگاهی بدون کد پیگیری');
        $rows = DB::table('zaban_orders')->where('status', 'paid')
            ->whereRaw("COALESCE(gateway, 'irankish') IN ('irankish', 'zarinpal')")
            ->where(fn ($q) => $q->whereNull('ref_id')->orWhere('ref_id', ''))
            ->where(fn ($q) => $q->whereNull('rrn')->orWhere('rrn', ''))
            ->get(['id', 'user_id', 'gateway', 'payable', 'paid_at']);
        $this->report($rows, ['سفارش', 'کاربر', 'درگاه', 'مبلغ (تومان)', 'زمان'],
            fn ($r) => [$r->id, $r->user_id, $r->gateway ?: 'irankish', number_format($r->payable), $r->paid_at]);

        $this->section('۳) مبلغ ریالی ناسازگار با مبلغ تومانی');
        $rows = DB::table('zaban_orders')->where('status', 'paid')
            ->whereRaw('amount_rial <> payable * 10')
            ->get(['id', 'user_id', 'gateway', 'payable', 'amount_rial']);
        $this->report($rows, ['سفارش', 'کاربر', 'درگاه', 'تومان', 'ریال'],
            fn ($r) => [$r->id, $r->user_id, $r->gateway, $r->payable, $r->amount_rial]);

        $this->section('۴) پرداخت دوباره برای همان رشته (کاندید برگشت وجه)');
        $paid = DB::table('zaban_orders')->where('status', 'paid')->where('payable', '>', 0)
            ->whereRaw("COALESCE(gateway, 'irankish') IN ('irankish', 'zarinpal')")
            ->orderBy('id')->get(['id', 'user_id', 'exams', 'payable', 'paid_at', 'gateway']);
        $seen = []; $dups = [];
        foreach ($paid as $o) {
            foreach (array_filter(explode(',', (string) $o->exams)) as $e) {
                $k = $o->user_id . '|' . $e;
                if (isset($seen[$k])) $dups[] = [$o->user_id, $e, $seen[$k], $o->id, number_format($o->payable), $o->gateway];
                else $seen[$k] = $o->id;
            }
        }
        $this->report(collect($dups), ['کاربر', 'رشته', 'سفارش اول', 'سفارش تکراری', 'مبلغ تکراری', 'درگاه'], fn ($r) => $r);

        $this->section('۵) دسترسی دستی (staff) برای دانشجوها — برای بازبینی');
        $rows = DB::table('zaban_entitlements as e')
            ->join('users as u', 'u.id', '=', 'e.user_id')
            ->where('e.source', 'staff')->whereNull('e.revoked_at')
            ->whereNotIn('u.type', Roles::STAFF)
            ->orderBy('e.user_id')
            ->get(['e.user_id', 'u.mobile', 'e.exam', 'e.order_id', 'e.granted_at', 'e.note']);
        /* این بخش «مشکل» حساب نمی‌شود — فعال‌سازی دستی کارت‌به‌کارت مجاز است؛ فقط فهرست */
        $this->listOnly($rows, ['کاربر', 'موبایل', 'رشته', 'سفارش', 'زمان', 'یادداشت'],
            fn ($r) => [$r->user_id, $r->mobile, $r->exam, $r->order_id ?: '—', $r->granted_at, mb_substr((string) $r->note, 0, 60)]);

        $this->section('۶) سفارش گیرکرده‌ی قدیمی‌تر از ۷ روز');
        $rows = DB::table('zaban_orders')->whereIn('status', ['pending', 'verifying'])
            ->whereNotNull('token')->where('created_at', '<', now()->subDays(7))
            ->get(['id', 'user_id', 'gateway', 'status', 'payable', 'created_at']);
        $this->report($rows, ['سفارش', 'کاربر', 'درگاه', 'وضعیت', 'مبلغ', 'زمان'],
            fn ($r) => [$r->id, $r->user_id, $r->gateway, $r->status, number_format($r->payable), $r->created_at]);

        $this->newLine();
        $this->issues
            ? $this->warn("{$this->issues} مورد نیاز به بررسی دارد. این دستور چیزی را تغییر نداده است.")
            : $this->info('مشکلی پیدا نشد.');
        return self::SUCCESS;
    }

    private function section(string $t): void
    {
        $this->newLine();
        $this->line("<options=bold>{$t}</>");
    }

    private function report($rows, array $head, callable $map): void
    {
        if (!count($rows)) { $this->line('  <fg=green>✓</> موردی نیست'); return; }
        $this->issues += count($rows);
        $this->line('  <fg=yellow>!</> ' . count($rows) . ' مورد');
        $this->table($head, collect($rows)->take(50)->map($map)->all());
        if (count($rows) > 50) $this->line('  … و ' . (count($rows) - 50) . ' مورد دیگر');
    }

    private function listOnly($rows, array $head, callable $map): void
    {
        if (!count($rows)) { $this->line('  موردی نیست'); return; }
        $this->line('  ' . count($rows) . ' مورد (فقط برای بازبینی)');
        $this->table($head, collect($rows)->take(50)->map($map)->all());
    }
}
