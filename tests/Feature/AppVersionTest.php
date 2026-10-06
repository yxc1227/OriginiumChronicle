<?php

namespace Tests\Feature;

use App\Support\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 系统版本号。
 *
 * 一个「版本号概念」要站得住，得同时满足三件事 —— 形状对、各处一致、真的露出来。
 * 三件事分开断言：少了任何一件，版本号都会慢慢退化成配置文件里一个没人看的字符串。
 *
 *  - 形状：语义化版本 x.y.z（本项目不用预发布后缀，免得发版时想太多）；
 *  - 一致：CHANGELOG 的首条、README 的徽章，都必须等于 config 里那个数 ——
 *    「改了没记」最典型的症状就是它们各自漂移；
 *  - 露出：页面顶栏有一枚（报障时说得清是哪一版），资产 URL 带缓存串
 *    （不带的话，改版后读者看到的还是缓存里的旧 CSS/JS）。
 */
class AppVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_version_looks_like_a_version(): void
    {
        $version = AppVersion::version();

        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            $version,
            '版本号不是 x.y.z 的形状：'.$version,
        );

        $this->assertSame('v'.$version, AppVersion::label());
    }

    public function test_the_changelog_opens_with_the_current_version(): void
    {
        $changelog = (string) file_get_contents(base_path('CHANGELOG.md'));

        $this->assertMatchesRegularExpression(
            '/^## '.preg_quote(AppVersion::version(), '/').' — '.preg_quote(AppVersion::releasedAt(), '/').'$/m',
            $changelog,
            'CHANGELOG.md 里没有当前版本 '.AppVersion::version().'（'.AppVersion::releasedAt()
                .'）的记录 —— 标题要写成 `## x.y.z — YYYY-MM-DD`，且两处与 config 一致',
        );
    }

    public function test_the_readme_badge_carries_the_current_version(): void
    {
        $readme = (string) file_get_contents(base_path('README.md'));

        $this->assertStringContainsString(
            'badge/version-'.AppVersion::version().'-',
            $readme,
            'README 的版本徽章与 config/app.php 的 version 不一致',
        );
    }

    public function test_the_version_is_visible_and_busts_asset_caches(): void
    {
        $response = $this->get('/')->assertOk();

        // 顶栏那一枚（第一屏内）与页脚那一处：版本号、发行日期都要落到页面上
        $response->assertSee('title="系统版本"', false);
        $response->assertSee('foot__ver', false);
        $response->assertSee(AppVersion::label(), false);
        $response->assertSee(AppVersion::releasedAt(), false);

        $html = (string) $response->getContent();

        $this->assertStringContainsString('assets/app.css?v='.AppVersion::version(), $html, '样式没带版本串');
        $this->assertStringContainsString('assets/app.js?v='.AppVersion::version(), $html, '脚本没带版本串');
    }
}
