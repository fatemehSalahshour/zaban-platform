<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ContentBuilder;
use App\Services\Entitlements;
use App\Services\Fsrs;
use App\Services\Payment\IranKish;
use App\Services\Pricing;
use App\Support\Jalali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * تست‌های مسیرهای حساس.
 *
 * روی دیتابیس MySQL «zaban_test» اجرا می‌شود (phpunit.xml)؛ ساختار از
 * database/schema/mysql-schema.sql. پیش از اجرا mysql باید در PATH باشد:
 *
 *   set PATH=C:\wamp64\bin\mysql\mysql9.1.0\bin;%PATH%
 *   php artisan test --filter=ZabanSecurity
 *
 * تا وقتی این فایل سبز نشده، پلتفرم را به روی کاربر باز نکنید.
 */
class ZabanSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * RefreshDatabase پیش از هر چیز کل دیتابیس را پاک می‌کند. اگر تنظیم
     * اشتباه باشد و به zaban_platform وصل شده باشیم، اینجا جلویش گرفته می‌شود.
     */
    protected function beforeRefreshingDatabase()
    {
        $db = config('database.connections.' . config('database.default') . '.database');
        if ($db !== 'zaban_test') {
            throw new \RuntimeException("تست‌ها فقط روی zaban_test اجرا می‌شوند، نه «{$db}». phpunit.xml را ببینید.");
        }
    }

    /* =================================================================
     |  آدرس‌های صفحه (router)
     * ================================================================= */

    public function test_every_platform_address_serves_the_app_and_needs_login(): void
    {
        foreach (['/zaban', '/zaban/words', '/zaban/word/ability', '/zaban/test/1404/ce/6'] as $url) {
            $this->get($url)->assertRedirect();                                /* مهمان ← ورود */
        }
        $user = $this->userWith(['ce']);
        foreach (['/zaban', '/zaban/words', '/zaban/word/ability', '/zaban/text/1404/ce/passage/2'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();                    /* همان فایل، هر آدرس */
        }
    }

    public function test_new_cards_per_day_admin_default_and_student_override(): void
    {
        $set  = app(\App\Services\Security\Settings::class);
        $user = $this->userWith(['ce']);

        $set->saveNewPerDay(30);                                   /* پیش‌فرض مدیر */
        $this->assertSame(30, $this->actingAs($user)->getJson('/api/me')->json('new_per_day'));

        $this->actingAs($user)->putJson('/api/profile',             /* انتخاب خود دانشجو */
            ['nickname' => '', 'exam' => 'ce', 'show_in_board' => true, 'new_per_day' => 12])->assertOk();
        $this->assertSame(12, $this->actingAs($user)->getJson('/api/me')->json('new_per_day'));

        $this->actingAs($user)->putJson('/api/profile',             /* خالی = برگشت به پیش‌فرض مدیر */
            ['nickname' => '', 'exam' => 'ce', 'show_in_board' => true, 'new_per_day' => null])->assertOk();
        $this->assertSame(30, $this->actingAs($user)->getJson('/api/me')->json('new_per_day'));

        $this->actingAs($user)->putJson('/api/profile',             /* بیرون از بازه رد می‌شود */
            ['nickname' => '', 'exam' => 'ce', 'show_in_board' => true, 'new_per_day' => 300])
             ->assertStatus(422)->assertJsonValidationErrors('new_per_day');
    }

    public function test_landing_page_for_guests_with_server_price_and_exam_date(): void
    {
        $pricing = app(Pricing::class);
        DB::table('zaban_meta')->updateOrInsert(['k' => 'exam_date'], ['v' => '2027-05-06 23:59:59']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('landing.css', $html);
        $this->assertStringContainsString(route('buy'), $html);           /* دکمه‌ی خرید واقعی */
        /* «شروع مرور کلمات» کار ورود را می‌کند؛ لینک جدای «ورود» برداشته شد */
        $this->assertStringContainsString(route('login'), $html);
        $this->assertStringNotContainsString('nav-login', $html);
        $this->assertStringContainsString('2027-05-06', $html);           /* تاریخ کنکور از سرور */
        $faPrice = strtr(number_format($pricing->bundles()[3]),
            ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹',','=>'٬']);
        $this->assertStringContainsString($faPrice, $html);               /* قیمت از جدول پنل */

        /* کاربر واردشده لندینگ را نمی‌بیند */
        $this->actingAs($this->userWith([]))->get('/')->assertRedirect(route('zaban'));
    }

    public function test_page_assets_are_versioned_and_shell_is_not_public(): void
    {
        /* جدا کردن کد، قدم ۱ و ۲: CSS و JS فایل جدا، با نسخه‌ی خودکار از زمان تغییر فایل */
        $html = $this->actingAs($this->userWith([]))->get('/zaban')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~/css/zaban\.css\?v=\d{8,}~', $html);
        $this->assertMatchesRegularExpression('~/js/app-bootstrap\.js\?v=\d{8,}~', $html);
        $this->assertMatchesRegularExpression('~/js/zaban/app\.js\?v=\d{8,}~', $html);
        /* کد صفحه دیگر داخل HTML نیست — قالب باید کوچک بماند */
        $this->assertLessThan(60_000, strlen($html), 'قالب صفحه بزرگ شده؛ کد یا استایل دوباره داخل HTML رفته است');
        $this->assertStringNotContainsString('<style>', $html);

        /* پوسته دیگر فایل عمومی نیست: بدون ورود هیچ‌جا سرو نمی‌شود */
        $this->assertFileDoesNotExist(public_path('vocab-platform-v69-wired.html'));
        $this->get('/vocab-platform-v69-wired.html')->assertNotFound();
    }

    /* =================================================================
     |  حق دسترسی
     * ================================================================= */

    public function test_user_without_package_cannot_read_content(): void
    {
        $user = $this->userWith([]);

        $this->actingAs($user)->getJson('/api/content?exam=ce')
             ->assertStatus(403)
             ->assertJsonPath('error', 'not_entitled');
    }

    public function test_user_cannot_read_other_exam_by_changing_url(): void
    {
        $user = $this->userWith(['ce']);

        $this->actingAs($user)->getJson('/api/content?exam=ce')->assertOk();
        $this->actingAs($user)->getJson('/api/content?exam=it')->assertStatus(403);
        $this->actingAs($user)->getJson('/api/content/questions?exam=it')->assertStatus(403);
    }

    public function test_expired_entitlement_loses_access(): void
    {
        $user = $this->userWith([]);
        app(Entitlements::class)->grant($user->id, 'ce', now()->subDay()->toDateTimeString());

        $this->actingAs($user)->getJson('/api/content?exam=ce')->assertStatus(403);
    }

    public function test_revoked_entitlement_loses_access_immediately(): void
    {
        $user = $this->userWith(['ce']);
        $this->actingAs($user)->getJson('/api/content?exam=ce')->assertOk();

        app(Entitlements::class)->revoke($user->id, 'ce', 'برگشت وجه');

        $this->actingAs($user)->getJson('/api/content?exam=ce')->assertStatus(403);
    }

    public function test_cannot_add_unowned_word_to_deck(): void
    {
        $user = $this->userWith(['ce']);
        $itWord = $this->wordIn('it');

        $this->actingAs($user)->postJson('/api/deck', ['t' => 'w', 'id' => $itWord, 'on' => true])
             ->assertOk()->assertJsonPath('changed', 0);

        $this->assertDatabaseMissing('deck_items', ['user_id' => $user->id, 'item_id' => $itWord]);
    }

    /* =================================================================
     |  نسخه‌ی نمایشی (سال دمو)
     * ================================================================= */

    public function test_demo_gives_only_the_demo_year(): void
    {
        $this->demo(1405, ['ce']);
        $user = $this->userWith([]);
        $in   = $this->wordIn('ce', 1405);
        $out  = $this->wordIn('ce', 1390);

        $res  = $this->actingAs($user)->getJson('/api/content?exam=ce')
                     ->assertOk()->assertJsonPath('demo_year', 1405);
        $body = $res->getContent();

        /* محتوای سال‌های دیگر اصلاً از سرور بیرون نمی‌رود */
        $this->assertStringContainsString($this->wordText($in), $body);
        $this->assertStringNotContainsString($this->wordText($out), $body);
    }

    public function test_demo_answer_only_for_demo_year(): void
    {
        $this->demo(1405, ['ce']);
        $user = $this->userWith([]);
        $qIn  = $this->questionIn('ce', 1405);
        $qOut = $this->questionIn('ce', 1390);

        $this->actingAs($user)->getJson("/api/question/$qIn/answer")->assertOk();
        $this->actingAs($user)->getJson("/api/question/$qOut/answer")->assertStatus(403);
    }

    public function test_demo_exam_only_for_demo_year(): void
    {
        $this->demo(1405, ['ce']);
        $user = $this->userWith([]);
        $this->questionIn('ce', 1405);                          /* شروع آزمون سؤال می‌خواهد */
        $this->questionIn('ce', 1390);
        $body = ['exam' => 'ce', 'mode' => 'washback', 'duration_sec' => 0];

        $this->actingAs($user)->postJson('/api/exam/start', $body + ['year' => 1390])->assertStatus(403);
        $this->actingAs($user)->postJson('/api/exam/start', $body + ['year' => 1405])->assertCreated();
    }

    public function test_demo_off_shows_paywall_again(): void
    {
        $this->demo(1405, ['ce']);
        $this->demo(null, []);
        $user = $this->userWith([]);

        $this->actingAs($user)->getJson('/api/content?exam=ce')->assertStatus(403);
    }

    public function test_demo_follows_profile_exam(): void
    {
        $this->demo(1405, ['ce']);              /* پیش‌فرض مدیر: فقط مهندسی کامپیوتر */
        $user = $this->userWith([]);
        DB::table('zaban_profiles')->insert(['user_id' => $user->id, 'nickname' => null, 'exam' => 'it']);

        $this->actingAs($user)->getJson('/api/content?exam=it')->assertOk()->assertJsonPath('demo_year', 1405);
        $this->actingAs($user)->getJson('/api/content?exam=ce')->assertStatus(403);
    }

    /* =================================================================
     |  کلید پاسخ
     * ================================================================= */

    public function test_content_never_contains_answer_key(): void
    {
        $user = $this->userWith(['ce']);
        $this->questionIn('ce', 1405);

        foreach (['/api/content?exam=ce', '/api/content/questions?exam=ce'] as $url) {
            $body = $this->actingAs($user)->getJson($url)->getContent();

            $this->assertStringNotContainsString('"correct"', $body);
            $this->assertStringNotContainsString('correct_option', $body);
            $this->assertStringNotContainsString('is_correct', $body);
            $this->assertStringNotContainsString('explanation', $body);
            $this->assertStringNotContainsString('"answer"', $body);
        }
    }

    public function test_assert_no_answer_key_actually_throws(): void
    {
        $this->expectException(\RuntimeException::class);

        app(ContentBuilder::class)->assertNoAnswerKey([
            'questions' => [['id' => 1, 'opts' => ['a'], 'correct_option' => 2]],
        ]);
    }

    public function test_answer_locked_during_feedback_exam(): void
    {
        $user = $this->userWith(['ce']);
        $qid  = $this->questionIn('ce', 1405);

        $this->actingAs($user)->postJson('/api/exam/start',
            ['year' => 1405, 'exam' => 'ce', 'mode' => 'feedback', 'duration_sec' => 3600])
             ->assertCreated();

        $this->actingAs($user)->getJson("/api/question/$qid/answer")
             ->assertStatus(409)
             ->assertJsonPath('error', 'answer_locked');
    }

    public function test_answers_batch_respects_lock_and_entitlement(): void
    {
        $user = $this->userWith(['ce']);
        $own  = $this->questionIn('ce', 1405);
        $not  = $this->questionIn('it', 1405);

        $this->actingAs($user)->postJson('/api/exam/start',
            ['year' => 1405, 'exam' => 'ce', 'mode' => 'feedback', 'duration_sec' => 3600])->assertCreated();

        $r = $this->actingAs($user)->getJson("/api/answers?ids=$own,$not")->assertOk();

        $this->assertSame([], $r->json('answers'));            /* هیچ پاسخی لو نرفت */
        $this->assertSame([$own], $r->json('locked'));         /* رشته‌ی خریده‌نشده بی‌صدا حذف شد */
    }

    public function test_answer_allowed_in_washback_mode(): void
    {
        $user = $this->userWith(['ce']);
        $qid  = $this->questionIn('ce', 1405);

        $this->actingAs($user)->postJson('/api/exam/start',
            ['year' => 1405, 'exam' => 'ce', 'mode' => 'washback', 'duration_sec' => 0]);

        $this->actingAs($user)->getJson("/api/question/$qid/answer")
             ->assertOk()->assertJsonStructure(['correct', 'explanation']);

        $this->assertDatabaseHas('answer_reveals',
            ['user_id' => $user->id, 'question_id' => $qid, 'reason' => 'washback']);
    }

    public function test_answer_forbidden_for_unowned_exam(): void
    {
        $user = $this->userWith(['ce']);
        $qid  = $this->questionIn('it', 1405);

        $this->actingAs($user)->getJson("/api/question/$qid/answer")->assertStatus(403);
    }

    /* =================================================================
     |  سقف روزانه‌ی پاسخ و هشدار به مدیر
     * ================================================================= */

    public function test_daily_answer_cap_limits_and_alerts_admin(): void
    {
        config(['zaban.answer_daily_cap' => 2]);
        $user = $this->userWith(['ce']);
        $q1 = $this->questionIn('ce', 1405, 1);
        $q2 = $this->questionIn('ce', 1405, 2);
        $q3 = $this->questionIn('ce', 1405, 3);

        $r = $this->actingAs($user)->getJson("/api/answers?ids=$q1,$q2,$q3")->assertOk();
        $this->assertCount(2, $r->json('answers'));
        $this->assertSame([$q3], $r->json('limited'));
        $this->assertDatabaseHas('security_alerts', ['user_id' => $user->id, 'kind' => 'answer_cap']);

        /* سؤالی که امروز دیده شده دوباره شمرده نمی‌شود */
        $this->actingAs($user)->getJson("/api/question/$q1/answer")->assertOk();
        /* سؤال تازه از مسیر تکی هم بسته است */
        $this->actingAs($user)->getJson("/api/question/$q3/answer")
             ->assertStatus(429)->assertJsonPath('error', 'daily_limit');
        /* هشدار روزی یک بار */
        $this->assertSame(1, DB::table('security_alerts')->where(['user_id' => $user->id, 'kind' => 'answer_cap'])->count());
    }

    public function test_exam_report_respects_daily_cap(): void
    {
        config(['zaban.answer_daily_cap' => 1]);
        $user = $this->userWith(['ce']);
        $this->questionIn('ce', 1405, 1);
        $this->questionIn('ce', 1405, 2);

        $id = $this->actingAs($user)->postJson('/api/exam/start',
            ['year' => 1405, 'exam' => 'ce', 'mode' => 'feedback', 'duration_sec' => 3600])->json('id');
        $key = $this->actingAs($user)->postJson("/api/exam/$id/finish")->assertOk()->json('key');

        /* نمره‌ها هست، ولی پاسخ‌نامه فقط تا سقف — ساختن و بستن پیاپی آزمون راه دور زدن نیست */
        $this->assertCount(2, $key);
        $this->assertSame(1, collect($key)->whereNotNull('correct')->count());
    }

    /* =================================================================
     |  بانک لغات: مثال‌ها کلمه‌به‌کلمه (امنیت محتوا، قدم ۱)
     * ================================================================= */

    public function test_content_bundle_has_no_examples(): void
    {
        $user = $this->userWith(['ce']);
        $w    = $this->wordIn('ce');
        $this->exampleFor($w, 'SECRET-EXAMPLE-SENTENCE');

        $body = $this->actingAs($user)->getJson('/api/content?exam=ce')->assertOk()->getContent();
        $this->assertStringNotContainsString('SECRET-EXAMPLE-SENTENCE', $body);

        $r = $this->actingAs($user)->getJson("/api/words/detail?ids=$w")->assertOk()->assertJsonPath('items.0.id', $w);
        $this->assertSame('SECRET-EXAMPLE-SENTENCE', app(\App\Services\Security\Watermark::class)->strip($r->json('items.0.ex.0.0')));
    }

    public function test_word_details_respect_entitlement_and_daily_cap(): void
    {
        config(['zaban.word_daily_cap' => 1]);
        $user = $this->userWith(['ce']);
        $a = $this->wordIn('ce');
        $b = $this->wordIn('ce');
        $x = $this->wordIn('it');                                /* رشته‌ی خریده‌نشده */

        $r = $this->actingAs($user)->getJson("/api/words/detail?ids=$a,$b,$x")->assertOk();
        $this->assertSame([$a], array_column($r->json('items'), 'id'));
        $this->assertSame([$b], $r->json('limited'));
        $this->assertDatabaseHas('security_alerts', ['user_id' => $user->id, 'kind' => 'word_cap']);

        /* کلمه‌ای که امروز گرفته شده دوباره شمرده نمی‌شود */
        $this->actingAs($user)->getJson("/api/words/detail?ids=$a")->assertOk()->assertJsonPath('items.0.id', $a);
    }

    /* =================================================================
     |  بانک لغات: معنی‌ها کلمه‌به‌کلمه (امنیت محتوا، قدم ۳)
     * ================================================================= */

    public function test_content_bundle_has_no_meanings(): void
    {
        $user = $this->userWith(['ce']);
        $w    = $this->wordIn('ce');
        DB::table('words')->where('id', $w)->update(['meaning_fa' => 'SECRET-MEANING']);

        $body = $this->actingAs($user)->getJson('/api/content?exam=ce')->assertOk()->getContent();
        $this->assertStringNotContainsString('SECRET-MEANING', $body);

        $fa = $this->actingAs($user)->getJson("/api/words/meanings?ids=$w")->assertOk()->json('items.0.fa');
        $this->assertSame('SECRET-MEANING', app(\App\Services\Security\Watermark::class)->strip($fa));
    }

    public function test_meanings_respect_entitlement_and_daily_cap(): void
    {
        config(['zaban.meaning_daily_cap' => 1]);
        $user = $this->userWith(['ce']);
        $a = $this->wordIn('ce'); $b = $this->wordIn('ce'); $x = $this->wordIn('it');

        $r = $this->actingAs($user)->getJson("/api/words/meanings?ids=$a,$b,$x")->assertOk();
        $this->assertSame([$a], array_column($r->json('items'), 'id'));
        $this->assertSame([$b], $r->json('limited'));
        $this->assertDatabaseHas('security_alerts', ['user_id' => $user->id, 'kind' => 'meaning_cap']);
    }

    public function test_meaning_search_returns_only_ids_in_scope(): void
    {
        $user = $this->userWith(['ce']);
        $own  = $this->wordIn('ce');
        $not  = $this->wordIn('it');
        DB::table('words')->whereIn('id', [$own, $not])->update(['meaning_fa' => 'پرتقال شیرین']);

        $r = $this->actingAs($user)->getJson('/api/words/search?q=' . urlencode('پرتقال'))->assertOk();
        $this->assertSame([$own], $r->json('ids'));
        $this->assertStringNotContainsString('شیرین', $r->getContent());      /* فقط شناسه، نه معنی */
    }

    public function test_admin_security_settings_override_config(): void
    {
        $admin = $this->userWith([]);
        DB::table('users')->where('id', $admin->id)->update(['type' => 'admin']);
        $b = app(Pricing::class)->bundles();

        $this->actingAs($admin->refresh())->post(route('zadmin.settings.save'), [
            'p1' => $b[1], 'p2' => $b[2], 'p3' => $b[3],
            'sec' => ['meaning_daily_cap' => 700, 'lock_hours' => 0],         /* ۰ زیر کمینه است */
        ])->assertRedirect();

        $set = app(\App\Services\Security\Settings::class);
        $this->assertSame(700, $set->get('meaning_daily_cap'));
        $this->assertSame(1, $set->get('lock_hours'));                         /* به کمینه برگشت */
        $this->assertFalse($set->listMeanings());                              /* تیک نخورده بود */
    }

    /* =================================================================
     |  امضای نامرئی و درهم‌سازی (امنیت محتوا، قدم ۴)
     * ================================================================= */

    public function test_meanings_and_examples_carry_the_users_invisible_signature(): void
    {
        $wm   = app(\App\Services\Security\Watermark::class);
        $user = $this->userWith(['ce']);
        $w    = $this->wordIn('ce');
        $this->exampleFor($w, 'A sentence to sign');

        $fa = $this->actingAs($user)->getJson("/api/words/meanings?ids=$w")->json('items.0.fa');
        $ex = $this->actingAs($user)->getJson("/api/words/detail?ids=$w")->json('items.0.ex.0.0');

        $this->assertSame([$user->id => 1], $wm->decode($fa));
        $this->assertSame([$user->id => 1], $wm->decode($ex));
    }

    public function test_leak_tool_identifies_the_source_account(): void
    {
        $thief = $this->userWith(['ce']);
        $w     = $this->wordIn('ce');
        $fa    = $this->actingAs($thief)->getJson("/api/words/meanings?ids=$w")->json('items.0.fa');

        $admin = $this->userWith([]);
        DB::table('users')->where('id', $admin->id)->update(['type' => 'admin']);

        $this->actingAs($admin->refresh())->post(route('zadmin.leak'), ['text' => "متن منتشرشده: $fa"])
             ->assertOk()->assertSee('شماره‌ی ' . $thief->id, false);
    }

    public function test_obfuscated_response_decodes_to_the_same_data(): void
    {
        $user = $this->userWith(['ce']);
        $w    = $this->wordIn('ce');
        $key  = bin2hex(random_bytes(16));

        $o = $this->actingAs($user)->withSession(['obf_key' => $key])->withHeaders(['X-Zaban-Obf' => '1'])
                  ->getJson("/api/words/meanings?ids=$w")->assertOk()->json('o');
        $this->assertIsString($o);                              /* در تب Network درهم است */

        $raw = base64_decode($o); $k = hex2bin($key); $plain = '';
        for ($i = 0; $i < strlen($raw); $i++) $plain .= $raw[$i] ^ $k[$i % 16];
        $this->assertSame($w, json_decode($plain, true)['items'][0]['id']);
    }

    /* =================================================================
     |  قفل خودکار روی الگوی اسکریپت (امنیت محتوا، قدم ۲)
     * ================================================================= */

    public function test_scripted_word_fetching_locks_the_account(): void
    {
        config(['zaban.lock_words_10min' => 2]);
        $user = $this->userWith(['ce']);
        $ids  = implode(',', [$this->wordIn('ce'), $this->wordIn('ce'), $this->wordIn('ce')]);

        $this->actingAs($user)->getJson("/api/words/detail?ids=$ids")->assertOk();

        $this->assertNotNull(DB::table('users')->where('id', $user->id)->value('locked_until'));
        $this->assertDatabaseHas('security_alerts', ['user_id' => $user->id, 'kind' => 'auto_lock']);

        $user->refresh();                                        /* قفل در حافظه هم دیده شود */
        $this->actingAs($user)->getJson('/api/content?exam=ce')
             ->assertStatus(423)->assertJsonPath('error', 'account_locked');
    }

    public function test_hitting_word_cap_on_consecutive_days_locks(): void
    {
        config(['zaban.word_daily_cap' => 1, 'zaban.lock_cap_streak' => 3]);
        $user = $this->userWith(['ce']);
        foreach ([1, 2] as $ago) {                               /* دیروز و پریروز هم به سقف خورده بود */
            DB::table('security_alerts')->insert(['user_id' => $user->id, 'kind' => 'word_cap',
                'day' => now()->subDays($ago)->toDateString(), 'created_at' => now()->subDays($ago)]);
        }
        $ids = implode(',', [$this->wordIn('ce'), $this->wordIn('ce')]);

        $this->actingAs($user)->getJson("/api/words/detail?ids=$ids")->assertOk();

        $this->assertNotNull(DB::table('users')->where('id', $user->id)->value('locked_until'));
    }

    public function test_staff_accounts_are_never_auto_locked(): void
    {
        config(['zaban.lock_words_10min' => 1]);
        $admin = $this->userWith(['ce']);
        DB::table('users')->where('id', $admin->id)->update(['type' => 'admin']);
        $ids = implode(',', [$this->wordIn('ce'), $this->wordIn('ce')]);

        $this->actingAs($admin->refresh())->getJson("/api/words/detail?ids=$ids")->assertOk();

        $this->assertNull(DB::table('users')->where('id', $admin->id)->value('locked_until'));
    }

    public function test_admin_can_unlock_an_account(): void
    {
        $user = $this->userWith(['ce']);
        DB::table('users')->where('id', $user->id)->update(['locked_until' => now()->addDay(), 'lock_reason' => 'تست']);
        $admin = $this->userWith([]);
        DB::table('users')->where('id', $admin->id)->update(['type' => 'admin']);

        $this->actingAs($admin->refresh())->post(route('zadmin.users.unlock', $user->id))->assertRedirect();

        $this->assertNull(DB::table('users')->where('id', $user->id)->value('locked_until'));
        $this->actingAs($user->refresh())->getJson('/api/content?exam=ce')->assertOk();
    }

    /* =================================================================
     |  یک حساب، یک دستگاه
     * ================================================================= */

    public function test_old_device_is_signed_out_when_another_logs_in(): void
    {
        config(['zaban.single_session' => true]);
        $user = $this->userWith([]);
        DB::table('users')->where('id', $user->id)->update(['session_token' => 'NEW-DEVICE']);
        $user->refresh();                                        /* شیء حافظه هم نشانه‌ی تازه را ببیند */

        $this->actingAs($user)->withSession(['device_token' => 'OLD-DEVICE'])->getJson('/api/me')
             ->assertStatus(401)->assertJsonPath('error', 'session_replaced');

        $user->refresh();
        $this->actingAs($user)->withSession(['device_token' => 'NEW-DEVICE'])->getJson('/api/me')->assertOk();
    }

    public function test_first_device_adopts_the_account(): void
    {
        config(['zaban.single_session' => true]);
        $user = $this->userWith([]);

        $this->actingAs($user)->getJson('/api/me')->assertOk();
        $this->assertNotNull(DB::table('users')->where('id', $user->id)->value('session_token'));
    }

    public function test_repeated_device_switching_alerts_admin(): void
    {
        /* یک درخواست کافی است: شمارنده‌ی جابه‌جایی امروز را یکی مانده به آستانه می‌گذاریم.
           چند درخواست پیاپی در یک تست، حالت نشست و احراز هویت تست را به‌هم می‌ریزد. */
        config(['zaban.single_session' => true, 'zaban.sharing_alert_kicks' => 2]);
        $user = $this->userWith([]);
        DB::table('users')->where('id', $user->id)->update(['session_token' => 'CURRENT']);
        $user->refresh();
        \Illuminate\Support\Facades\Cache::put('zaban.kicks.' . $user->id . '.' . now()->toDateString(), 1, now()->endOfDay());

        $this->actingAs($user)->withSession(['device_token' => 'OLD'])->getJson('/api/me')->assertStatus(401);
        $this->assertDatabaseHas('security_alerts', ['user_id' => $user->id, 'kind' => 'account_sharing']);
    }

    /* =================================================================
     |  نمره‌ی آزمون — گزینه‌ی صفرپایه‌ی رابط در برابر correct_option یک‌پایه
     * ================================================================= */

    public function test_first_option_is_graded_not_blank(): void
    {
        $user = $this->userWith(['ce']);
        $this->questionIn('ce', 1405);                          /* پاسخ درست: گزینه‌ی ۲ */

        $id = $this->actingAs($user)->postJson('/api/exam/start',
            ['year' => 1405, 'exam' => 'ce', 'mode' => 'feedback', 'duration_sec' => 3600])->json('id');

        /* رابط اندیس صفرپایه می‌فرستد: ۱ یعنی گزینه‌ی دوم */
        $this->actingAs($user)->patchJson("/api/exam/$id", ['state' => ['ans' => ['1' => 1]]])->assertOk();
        $r = $this->actingAs($user)->postJson("/api/exam/$id/finish")->assertOk();

        $this->assertSame(1, $r->json('correct'));
        $this->assertSame(0, $r->json('blank'));
    }

    /* =================================================================
     |  کش محتوا
     * ================================================================= */

    public function test_content_returns_304_on_matching_etag(): void
    {
        $user = $this->userWith(['ce']);

        $first = $this->actingAs($user)->getJson('/api/content?exam=ce')->assertOk();
        $etag  = $first->headers->get('ETag');

        $this->assertNotEmpty($etag);
        $this->assertStringContainsString('no-cache', $first->headers->get('Cache-Control'));

        $this->actingAs($user)
             ->withHeaders(['If-None-Match' => $etag])
             ->getJson('/api/content?exam=ce')
             ->assertStatus(304);
    }

    public function test_etag_changes_after_content_bump(): void
    {
        $user = $this->userWith(['ce']);
        $a = $this->actingAs($user)->getJson('/api/content?exam=ce')->headers->get('ETag');

        sleep(1);
        app(ContentBuilder::class)->bump('ce');

        $b = $this->actingAs($user)->getJson('/api/content?exam=ce')->headers->get('ETag');
        $this->assertNotSame($a, $b);
    }

    /* =================================================================
     |  قیمت و خرید (درگاه آزمایشی)
     * ================================================================= */

    public function test_price_comes_from_server_not_client(): void
    {
        $this->fakeGateway();
        $user = $this->userWith([]);
        $want = app(Pricing::class)->bundles()[3];

        /* مرورگر هر چه بفرستد، فقط exams خوانده می‌شود */
        $this->actingAs($user)->post('/buy',
            ['exams' => ['ce', 'it', 'cs'], 'payable' => 1000, 'discount' => 999999])->assertOk();

        $this->assertDatabaseHas('zaban_orders', [
            'user_id' => $user->id, 'payable' => $want, 'amount_rial' => $want * 10, 'status' => 'pending',
        ]);
    }

    public function test_already_owned_exam_is_not_billed_again(): void
    {
        $q = app(Pricing::class)->quote(['ce', 'it'], ['ce']);

        $this->assertSame(['it'], $q['billable']);
        $this->assertSame(app(Pricing::class)->bundles()[1], $q['payable']);
    }

    public function test_successful_payment_grants_access(): void
    {
        $this->fakeGateway();
        $user  = $this->userWith([]);
        $order = $this->startOrder($user, ['it']);

        $this->post('/buy/return', $this->gatewayReturn($order, '00'))
             ->assertRedirect(route('buy.result', $order->id));

        $this->assertDatabaseHas('zaban_orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('zaban_entitlements', ['user_id' => $user->id, 'exam' => 'it', 'order_id' => $order->id]);
        $this->actingAs($user)->getJson('/api/content?exam=it')->assertOk();
    }

    public function test_failed_payment_grants_nothing(): void
    {
        $this->fakeGateway();
        $user  = $this->userWith([]);
        $order = $this->startOrder($user, ['it']);

        $this->post('/buy/return', $this->gatewayReturn($order, '55'));

        $this->assertDatabaseHas('zaban_orders', ['id' => $order->id, 'status' => 'failed', 'gateway_code' => '55']);
        $this->assertDatabaseMissing('zaban_entitlements', ['user_id' => $user->id, 'exam' => 'it']);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $this->fakeGateway();
        $user  = $this->userWith([]);
        $order = $this->startOrder($user, ['it']);

        $in = $this->gatewayReturn($order, '00');
        $in['amount'] = 10;                                     /* مبلغ دست‌کاری‌شده */
        $this->post('/buy/return', $in);

        $this->assertDatabaseHas('zaban_orders', ['id' => $order->id, 'status' => 'failed']);
        $this->assertDatabaseMissing('zaban_entitlements', ['user_id' => $user->id, 'exam' => 'it']);
    }

    public function test_double_return_is_processed_once(): void
    {
        $this->fakeGateway();
        $user  = $this->userWith([]);
        $order = $this->startOrder($user, ['it']);
        $in    = $this->gatewayReturn($order, '00');

        $this->post('/buy/return', $in);
        $in['responseCode'] = '55';                             /* برگشت دوم نباید چیزی را عوض کند */
        $this->post('/buy/return', $in);

        $this->assertDatabaseHas('zaban_orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertSame(1, DB::table('zaban_entitlements')->where(['user_id' => $user->id, 'exam' => 'it'])->count());
    }

    public function test_unknown_token_does_not_crash(): void
    {
        $this->post('/buy/return', ['token' => 'NOPE', 'responseCode' => '00'])->assertRedirect(route('buy'));
    }

    public function test_grant_is_idempotent(): void
    {
        $user = $this->userWith([]);
        $ent  = app(Entitlements::class);

        $ent->grant($user->id, 'ce', null);
        $ent->grant($user->id, 'ce', null);

        $this->assertSame(1, DB::table('zaban_entitlements')
            ->where(['user_id' => $user->id, 'exam' => 'ce'])->count());
    }

    /* =================================================================
     |  تاریخ کنکور (شمسی ↔ میلادی)
     * ================================================================= */

    public function test_jalali_conversion(): void
    {
        $this->assertSame('2027-05-06 23:59:59', Jalali::parseToGregorian('1406/02/16'));
        $this->assertSame('2026-09-22 08:00:00', Jalali::parseToGregorian('۱۴۰۵/۰۶/۳۱ 08:00'));
        $this->assertSame('2025-03-20 23:59:59', Jalali::parseToGregorian('1403-12-30'));   /* اسفند کبیسه */
        $this->assertNull(Jalali::parseToGregorian('1402/12/30'));                          /* غیرکبیسه */
        $this->assertNull(Jalali::parseToGregorian('فردا'));
        $this->assertSame('1406-02-16 23:59', Jalali::formatFromGregorian('2027-05-06 23:59:59'));
    }

    public function test_legacy_jalali_exam_date_is_converted(): void
    {
        /* پیش از اصلاح، فرم پنل تاریخ شمسی را خام ذخیره می‌کرد */
        DB::table('zaban_meta')->insert(['k' => 'exam_date', 'v' => '1406-02-16 23:59:59']);

        $this->assertSame('2027-05-06 23:59:59', app(Pricing::class)->accessUntil());
    }

    /* =================================================================
     |  پروفایل و یادداشت
     * ================================================================= */

    public function test_profile_allows_empty_nickname(): void
    {
        $user = $this->userWith([]);

        $this->actingAs($user)->putJson('/api/profile', ['nickname' => '', 'exam' => 'ce', 'show_in_board' => true])
             ->assertOk();
        $this->assertDatabaseHas('zaban_profiles', ['user_id' => $user->id, 'nickname' => null]);
    }

    public function test_duplicate_nickname_is_rejected_politely(): void
    {
        $a = $this->userWith([]);
        $b = $this->userWith([]);
        $this->actingAs($a)->putJson('/api/profile', ['nickname' => 'کوانتوم', 'exam' => 'ce', 'show_in_board' => true])->assertOk();

        $this->actingAs($b)->putJson('/api/profile', ['nickname' => 'کوانتوم', 'exam' => 'ce', 'show_in_board' => true])
             ->assertStatus(422)->assertJsonValidationErrors('nickname');
    }

    public function test_note_can_be_deleted_with_empty_body(): void
    {
        $user = $this->userWith(['ce']);
        $w    = $this->wordIn('ce');

        $this->actingAs($user)->putJson('/api/note', ['t' => 'w', 'id' => $w, 'body' => 'یادداشت'])->assertOk();
        $this->actingAs($user)->putJson('/api/note', ['t' => 'w', 'id' => $w, 'body' => ''])->assertOk();

        $this->assertDatabaseMissing('user_notes', ['user_id' => $user->id, 'item_id' => $w]);
    }

    /* =================================================================
     |  آزمون و زمان
     * ================================================================= */

    public function test_exam_start_resumes_open_attempt(): void
    {
        $user = $this->userWith(['ce']);
        $this->questionIn('ce', 1405);                          /* بدون سؤال: 422 no_questions */
        $body = ['year' => 1405, 'exam' => 'ce', 'mode' => 'feedback', 'duration_sec' => 3600];

        $a = $this->actingAs($user)->postJson('/api/exam/start', $body)->json('id');
        $b = $this->actingAs($user)->postJson('/api/exam/start', $body);

        $b->assertOk()->assertJsonPath('resumed', true)->assertJsonPath('id', $a);
    }

    public function test_answers_after_deadline_do_not_count(): void
    {
        $user = $this->userWith(['ce']);
        $this->questionIn('ce', 1405);
        $id = $this->actingAs($user)->postJson('/api/exam/start',
            ['year' => 1405, 'exam' => 'ce', 'mode' => 'feedback', 'duration_sec' => 60])->json('id');

        $this->travel(120)->seconds();

        $this->actingAs($user)->patchJson("/api/exam/$id", ['state' => ['ans' => ['1' => 1]]])
             ->assertStatus(409)->assertJsonPath('error', 'deadline_passed');

        $this->assertDatabaseHas('exam_attempts', ['id' => $id, 'auto_closed' => 1]);
    }

    public function test_heartbeat_rejects_too_frequent_calls(): void
    {
        $user = $this->userWith(['ce']);

        $this->actingAs($user)->postJson('/api/heartbeat')->assertOk();
        $this->actingAs($user)->postJson('/api/heartbeat')->assertStatus(429);
    }

    /* =================================================================
     |  الگوریتم مرور (FSRS-6)
     * ================================================================= */

    public function test_forgetting_shortens_interval(): void
    {
        /* Fsrs فقط زمان‌بندی را حساب می‌کند؛ شمارش lapses با کنترلر است (تست بعدی) */
        $f = app(Fsrs::class);

        $card = $f->fromSm2(2.5, 21);
        $next = $f->review($card, 1, 21);                      /* «یادم نبود» */

        $this->assertLessThan(21, $next['interval_days'], 'فاصله باید کوتاه شود');
    }

    public function test_interval_never_passes_the_exam_date(): void
    {
        /* کارتی که خیلی پایدار شده بازه‌ی چندساله می‌گیرد. بدون سقف، تا بعد از
           کنکور برنمی‌گشت — یعنی دانشجو کلمه‌ی «مسلط» را ماه‌ها پیش از جلسه
           آخرین بار دیده بود. */
        $f    = app(Fsrs::class);
        $card = ['stability' => 400.0, 'difficulty' => 3.0];

        $free = $f->review($card, Fsrs::EASY, 30)['interval_days'];
        $this->assertGreaterThan(60, $free, 'کارت پایدار باید بازه‌ی بلند بگیرد');

        $capped = $f->horizon(now()->addDays(40))->review($card, Fsrs::EASY, 30);
        $this->assertLessThanOrEqual(39, $capped['interval_days'],
            'بازه نباید از روز کنکور بگذرد (روز آخر هم کنار گذاشته می‌شود)');
        $this->assertLessThan(now()->addDays(40)->toDateString(), $capped['due_date']);
    }

    public function test_fuzz_spreads_cards_but_stays_stable_per_card(): void
    {
        $card = ['stability' => 30.0, 'difficulty' => 5.0];

        /* یک کارت: هر بار همان عدد — وگرنه عددِ روی دکمه با آنچه ثبت می‌شود
           فرق می‌کرد و کاربر فکر می‌کرد سیستم دروغ گفته. */
        $a = app(Fsrs::class)->seed('w:412')->review($card, Fsrs::GOOD, 30)['interval_days'];
        $b = app(Fsrs::class)->seed('w:412')->review($card, Fsrs::GOOD, 30)['interval_days'];
        $this->assertSame($a, $b, 'یک کارت باید همیشه همان بازه را بگیرد');

        /* کارت‌های مختلف: پخش می‌شوند تا همه یک روز با هم برنگردند */
        $seen = [];
        foreach (range(1, 40) as $i) {
            $seen[] = app(Fsrs::class)->seed('w:' . $i)->review($card, Fsrs::GOOD, 30)['interval_days'];
        }
        $this->assertGreaterThan(1, count(array_unique($seen)), 'بازه‌ها باید پخش شوند');
    }

    public function test_personal_weights_are_used_only_after_acceptance(): void
    {
        $params = app(\App\Services\FsrsParams::class);
        $user   = $this->userWith(['ce']);

        /* وزن دست‌کاری‌شده که اثرش در بازه دیده می‌شود */
        $odd = \App\Services\Fsrs::DEFAULT_W;
        $odd[8] = $odd[8] * 3;

        DB::table('zaban_fsrs_params')->insert([
            'user_id' => $user->id, 'exam' => '', 'w' => json_encode($odd),
            'accepted' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        /* رد شده → باید همان پیش‌فرض بماند */
        $this->assertSame(\App\Services\Fsrs::DEFAULT_W, (new \App\Services\FsrsParams)->weights($user->id));

        DB::table('zaban_fsrs_params')->where('user_id', $user->id)->update(['accepted' => true]);
        $this->assertNotSame(\App\Services\Fsrs::DEFAULT_W, (new \App\Services\FsrsParams)->weights($user->id));

        /* کاربر دیگری نباید تنظیم این یکی را بگیرد */
        $other = $this->userWith(['ce']);
        $this->assertSame(\App\Services\Fsrs::DEFAULT_W, (new \App\Services\FsrsParams)->weights($other->id));
    }

    public function test_broken_weights_fall_back_to_defaults(): void
    {
        /* یک ردیف خراب نباید کل مرور را بخواباند */
        $user = $this->userWith(['ce']);
        DB::table('zaban_fsrs_params')->insert([
            'user_id' => $user->id, 'exam' => '', 'w' => json_encode([1, 2, 3]),
            'accepted' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(\App\Services\Fsrs::DEFAULT_W, (new \App\Services\FsrsParams)->weights($user->id));
    }

    public function test_optimizer_needs_enough_data(): void
    {
        /* با دیتابیس خالی نباید چیزی بسازد — نه خطا بدهد، نه وزن بی‌پشتوانه */
        $this->artisan('zaban:fsrs-optimize', ['--global' => true])->assertSuccessful();
        $this->assertDatabaseCount('zaban_fsrs_params', 0);
    }

    public function test_review_forgot_counts_lapse(): void
    {
        $user = $this->userWith(['ce']);
        $w    = $this->wordIn('ce');

        $this->actingAs($user)->postJson('/api/deck', ['t' => 'w', 'id' => $w, 'on' => true])->assertOk();
        $this->actingAs($user)->postJson('/api/review', ['t' => 'w', 'id' => $w, 'rating' => 1])->assertOk();

        $this->assertDatabaseHas('deck_items', [
            'user_id' => $user->id, 'item_type' => 'word', 'item_id' => $w,
            'lapses' => 1, 'again_count' => 1, 'mastered_at' => null,
        ]);
    }

    public function test_difficulty_stays_within_bounds(): void
    {
        $f = app(Fsrs::class);
        $card = $f->fromSm2(1.3, 1);

        for ($i = 0; $i < 10; $i++) $card = $f->review($card, 1, 1);
        $this->assertLessThanOrEqual(10.0, $card['difficulty']);

        for ($i = 0; $i < 10; $i++) $card = $f->review($card, 4, 1);
        $this->assertGreaterThanOrEqual(1.0, $card['difficulty']);
    }

    /* =================================================================
     |  کمکی — RefreshDatabase هر بار دیتابیس را خالی می‌کند؛ هر تست
     |  محتوای لازم خودش را می‌سازد.
     * ================================================================= */

    private function userWith(array $exams): User
    {
        $user = User::create([
            'name'     => 'تست',
            'email'    => 'u' . uniqid() . '@test.local',
            'password' => bcrypt('secret123'),
        ]);

        foreach ($exams as $e) {
            app(Entitlements::class)->grant($user->id, $e, null, 'staff');
        }
        return $user;
    }

    private function wordIn(string $exam, int $year = 1405): int
    {
        $id = DB::table('words')->insertGetId([
            'word'       => 'w' . uniqid(),
            'meaning_fa' => 'معنی تست',
            'level'      => 'متوسط',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('word_occurrences')->insert([
            'word_id' => $id, 'year' => $year, 'exam' => $exam,
            'section' => 'vocab', 'test_number' => 1,
            'slot' => 'vocab:1', 'source' => 'question',
        ]);
        return $id;
    }

    private function exampleFor(int $wordId, string $sentence): void
    {
        DB::table('word_examples')->insert([
            'word_id' => $wordId, 'sentence' => $sentence, 'sentence_fa' => 'ترجمه',
            'sentence_hash' => md5($sentence),
        ]);
    }

    private function wordText(int $id): string
    {
        return (string) DB::table('words')->where('id', $id)->value('word');
    }

    private function questionIn(string $exam, int $year, int $number = 1): int
    {
        DB::table('exam_sections')->insert([
            'year' => $year, 'exam' => $exam, 'section' => 'vocab',
            'passage_number' => null, 'from_question' => 1,
            'to_question' => 10, 'sort_order' => 1,
        ]);

        $qid = DB::table('questions')->insertGetId([
            'year' => $year, 'exam' => $exam, 'question_number' => $number,
            'section' => 'vocab', 'stem' => 'A test stem ......',
            'correct_option' => 2, 'explanation' => 'توضیح تست',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([1, 2, 3, 4] as $p) {
            DB::table('question_options')->insert([
                'question_id' => $qid, 'position' => $p,
                'body' => 'option ' . $p, 'is_correct' => $p === 2 ? 1 : 0,
            ]);
        }
        return $qid;
    }

    private function demo(?int $year, array $exams): void
    {
        app(Entitlements::class)->saveDemo($year, $exams);
    }

    /** درگاه آزمایشی بدون APP_ENV=local — فقط در همین تست */
    private function fakeGateway(): void
    {
        $this->app->instance(IranKish::class, new class extends IranKish {
            public function fake(): bool { return true; }
        });
    }

    private function startOrder(User $user, array $exams): object
    {
        $this->actingAs($user)->post('/buy', ['exams' => $exams])->assertOk();
        return DB::table('zaban_orders')->where('user_id', $user->id)->orderByDesc('id')->first();
    }

    /** همان فیلدهایی که ایران کیش با POST مرورگر به revertUri می‌فرستد */
    private function gatewayReturn(object $order, string $code): array
    {
        return [
            'token' => $order->token, 'acceptorId' => 'FAKE', 'responseCode' => $code,
            'RequestId' => $order->request_id, 'amount' => $order->amount_rial,
            'retrievalReferenceNumber' => '123456789012', 'systemTraceAuditNumber' => '123456',
            'maskedPan' => '603799******1234',
        ];
    }
}
