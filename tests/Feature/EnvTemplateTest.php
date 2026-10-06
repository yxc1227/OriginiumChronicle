<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 环境变量模板。
 *
 * `.env.example` 是**入库**的那一份，它是「这个项目能调到什么」的地图 ——
 * 但它与 config 之间没有语言层面的约束：新加一个 env 键、忘了写进模板，谁也不会红。
 * 于是这张地图会慢慢只剩一半真相，比没有地图更糟（照着它配，配不出该有的行为）。
 *
 * 这里只守**本项目自己的键**（TIMELINE_ / IDENTITY_ / HYPERGRYPH_ 前缀）：
 * Laravel 骨架自带的那一堆（AUTH_ / MAIL_ / LOG_ / SESSION_ …）有意不列进模板 ——
 * 它们的默认值在框架 config 里已经写全，模板里再抄一遍只会淹没真正要看的几行。
 */
class EnvTemplateTest extends TestCase
{
    /** 本项目自有 env 键的前缀。 */
    private const PROJECT_PREFIXES = ['TIMELINE_', 'IDENTITY_', 'HYPERGRYPH_'];

    public function test_the_example_env_documents_every_project_key(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        foreach ($this->projectKeys() as $key) {
            $this->assertMatchesRegularExpression(
                '/^#?\s*'.preg_quote($key, '/').'=/m',
                $example,
                "config 里读了 {$key}，但 .env.example 里没有它 —— 新加的可调项要写进模板（注释掉也算，"
                    .'模板的作用是「知道有哪些可调」，不是「必须配齐」）',
            );
        }
    }

    public function test_the_example_env_carries_neither_a_key_nor_a_version(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        // 模板是入库文件：留一个真实 APP_KEY 等于把它提交进仓库
        $this->assertMatchesRegularExpression(
            '/^APP_KEY=$/m',
            $example,
            '.env.example 里的 APP_KEY 必须是空的（它会被提交）',
        );

        // 版本号的正源是 config/app.php —— 模板里写一份，发版时就多出一个会漏改的真相
        $this->assertMatchesRegularExpression(
            '/^#\s*APP_VERSION=/m',
            $example,
            '.env.example 里的 APP_VERSION 应当是注释掉的：正源在 config/app.php，见 CHANGELOG.md',
        );
    }

    /**
     * config/ 里读到的、属于本项目自己的 env 键。
     *
     * @return list<string>
     */
    private function projectKeys(): array
    {
        $keys = [];

        foreach (glob(config_path('*.php')) ?: [] as $file) {
            preg_match_all("/env\('([A-Z0-9_]+)'/", (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $key) {
                foreach (self::PROJECT_PREFIXES as $prefix) {
                    if (str_starts_with($key, $prefix)) {
                        $keys[$key] = true;
                    }
                }
            }
        }

        return array_keys($keys);
    }
}
