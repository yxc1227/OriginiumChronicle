<?php

namespace App\Support;

/**
 * 系统版本号：取数、给人看的写法、以及它在页面上的实际用途。
 *
 * 版本号本身**只有一个信息源**（config/app.php 的 `version`，可被 .env 的 APP_VERSION 覆盖）。
 * 发布记录写在仓库根目录的 CHANGELOG.md，README 的徽章行也带一份 ——
 * 三处不许漂移，由 `tests/Feature/AppVersionTest.php` 逐条比对。
 *
 * 除了给人看（label），它还承担一件实际的事：**资产缓存串**（asset）。
 * 站点的 CSS/JS 是零构建的静态文件，浏览器按 URL 缓存 —— 不带上版本号时，
 * 改版后读者会继续看到旧样式，只能靠强刷（改字号那次就撞过：测试全绿，页面却是旧的）。
 *
 * 代价是一条纪律：**改了 public/assets/ 下的 CSS/JS，就要把版本号 bump 一格**
 * （PATCH 也算一次发行，理由写在 CHANGELOG 的开头）。
 */
class AppVersion
{
    /** 版本号本体，如 1.0.0。 */
    public static function version(): string
    {
        return (string) config('app.version');
    }

    /** 给人看的写法：v1.1.0。 */
    public static function label(): string
    {
        return 'v'.self::version();
    }

    /**
     * 当前版本的发行日期，如 2026-09-30。
     *
     * 与版本号同源同规矩（config 是唯一信息源、CHANGELOG 首条同日、测试守着两边）。
     * 单独存一项而不是运行期去解析 CHANGELOG：页脚每页都渲染，不该为此读文件。
     */
    public static function releasedAt(): string
    {
        return (string) config('app.released_at');
    }

    /**
     * 带版本串的资产 URL。
     *
     * 用查询串而不是文件名指纹：静态文件是**零构建**的，路径要可读、可直连
     * （`/assets/app.css` 是给人看的），指纹方案会把路径变成一串不可读的散列。
     */
    public static function asset(string $path): string
    {
        return asset($path).'?v='.self::version();
    }
}
