<?php

namespace App\Console\Commands;

use App\Services\Payment\Checkout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * سفارش‌هایی که وسط راه مانده‌اند:
 *   - verifying و هنوز در مهلت ۲۰ دقیقه‌ی تاییدیه → تاییدیه دوباره
 *   - pending/verifying قدیمی‌تر از ۲۵ دقیقه → استعلام از ایران کیش؛
 *     اگر درگاه می‌گوید تایید شده، دسترسی داده می‌شود، وگرنه بسته می‌شود.
 * استعلام ایران کیش فقط تا ۷ روز جواب می‌دهد؛ قدیمی‌تر را نمی‌پرسیم.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'zaban:reconcile-payments';
    protected $description = 'تطبیق سفارش‌های پرداخت نیمه‌کاره با درگاه ایران کیش';

    public function handle(Checkout $checkout): int
    {
        $orders = DB::table('zaban_orders')
            ->whereIn('status', ['pending', 'verifying'])
            ->where('created_at', '>=', now()->subDays(7))
            ->where('created_at', '<=', now()->subMinutes(2))
            ->whereNotNull('token')
            ->orderBy('id')->limit(200)->get();

        $tally = [];
        foreach ($orders as $o) {
            $r = $checkout->reconcile($o);
            $tally[$r] = ($tally[$r] ?? 0) + 1;
        }

        $this->info($orders->count() . ' سفارش بررسی شد ' . json_encode($tally, JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }
}
