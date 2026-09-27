<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Race;
use App\Support\Search\Keyword;
use App\Support\Search\Params;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\BuildsTimeline;
use Tests\TestCase;

/**
 * 全站检索的一致性。
 *
 * 规矩的唯一定义在 `App\Support\Search\Keyword`（关键词）与 `Params`（参数读法）。
 * 这里从两个层面钉住它：
 *   · 单元层：归一化 / 转义 / 字面包含的边界（「0」、空白、通配符）；
 *   · HTTP 层：**各页真的用到了它** —— 断言的是行为，不是实现细节。
 *
 * 从前十个模块各写各的，长出三套转义、两种大小写口径，以及「同一个词在 A 页搜得到、
 * 在 B 页搜不到」。这些用例正是那批差异的回归网。
 *
 * 夹具自建（不依赖 seeder）：与其余 Feature 测试同一约定。
 */
class SearchConsistencyTest extends TestCase
{
    use BuildsTimeline, RefreshDatabase;

    // ---------------------------------------------------------------- 单元层

    public function test_keyword_normalisation_keeps_zero_and_drops_blanks(): void
    {
        // 「0」是有效关键词 —— `trim(...) ?: null` 那种写法会把它当空值吞掉
        $this->assertSame('0', Keyword::normalize('0'));
        $this->assertSame('0', Keyword::normalize(' 0 '));

        $this->assertNull(Keyword::normalize(''));
        $this->assertNull(Keyword::normalize('   '));
        $this->assertNull(Keyword::normalize(null));
        $this->assertNull(Keyword::normalize([]));

        $this->assertSame('阿米娅', Keyword::normalize(' 阿米娅 '));
    }

    public function test_keyword_escapes_wildcards_with_a_portable_escape_character(): void
    {
        // 转义符选 `!`：两个方言都能照写 `escape '!'`（`\` 在 MySQL 里要先写成 `\\`，
        // 而 SQLite 根本不认默认转义符 —— 从前那套转义在测试库上是不成立的）
        $this->assertSame('!%', Keyword::escape('%'));
        $this->assertSame('!_', Keyword::escape('_'));
        $this->assertSame('!!', Keyword::escape('!'));
        // 反斜杠不再是转义符，按普通字符原样送出
        $this->assertSame('\\', Keyword::escape('\\'));
    }

    public function test_php_side_containment_matches_the_sql_side(): void
    {
        $this->assertTrue(Keyword::contains('Rhodes Island', 'rhodes'));
        $this->assertFalse(Keyword::contains('Rhodes Island', 'columbia'));
        // 没给词就是不筛
        $this->assertTrue(Keyword::contains('Rhodes Island', '  '));
        // PHP 这条路上 `%` 与 `_` 本来就是字面量
        $this->assertTrue(Keyword::contains('100%', '%'));
    }

    public function test_params_only_accept_positive_ids(): void
    {
        $this->assertSame(12, Params::id($this->request(['faction_id' => ' 12 ']), 'faction_id'));
        // 正名找不到时回落到别名 —— 旧链接不该因为这次统一而失效
        $this->assertSame(12, Params::id($this->request(['faction' => '12']), 'faction_id', 'faction'));
        $this->assertNull(Params::id($this->request(['faction_id' => '0']), 'faction_id'));
        $this->assertNull(Params::id($this->request(['faction_id' => '-3']), 'faction_id'));
        $this->assertNull(Params::id($this->request(['faction_id' => 'abc']), 'faction_id'));
        $this->assertNull(Params::id($this->request(['faction_id' => ['1', '2']]), 'faction_id'));

        $this->assertSame([1, 2], Params::ids($this->request(['tag_ids' => ['1', '2', '2', 'x']]), 'tag_ids'));
        $this->assertNull(Params::ids($this->request(['tag_ids' => ['0', 'x']]), 'tag_ids'));
    }

    // ---------------------------------------------------------------- HTTP 层：干员页

    public function test_operator_search_keeps_zero_as_a_keyword_instead_of_dropping_it(): void
    {
        $this->operator(['name' => '阿米娅']);

        // 从前的 `trim(...) ?: null` 会把「0」当空值 —— 页面于是返回**全量**列表。
        // 正确行为：拿字面「0」去搜，搜不到就该一条都不命中
        $this->get(route('operators.index', ['q' => '0']))
            ->assertOk()
            ->assertDontSee('阿米娅');
    }

    public function test_operator_search_ignores_surrounding_whitespace(): void
    {
        $this->operator(['name' => '阿米娅']);

        $this->get(route('operators.index', ['q' => '  阿米娅  ']))
            ->assertOk()
            ->assertSee('阿米娅');

        // 纯空白等于没筛（与「重置」同义）
        $this->get(route('operators.index', ['q' => '   ']))
            ->assertOk()
            ->assertSee('阿米娅');
    }

    public function test_operator_search_is_case_insensitive_and_treats_wildcards_as_literal(): void
    {
        $this->operator(['name' => '阿米娅', 'codename' => 'Amiya']);

        $this->get(route('operators.index', ['q' => 'amiya']))
            ->assertOk()
            ->assertSee('阿米娅');

        // `%` 是字面量：不该命中全部
        $this->get(route('operators.index', ['q' => '%']))
            ->assertOk()
            ->assertDontSee('阿米娅');
    }

    public function test_operator_search_covers_profile_text_and_faction_names(): void
    {
        $faction = $this->faction('检索测试阵营');
        $character = $this->operator([
            'name' => '阿米娅',
            'description' => '与众不同的简介片段',
        ]);
        $character->factions()->attach($faction->id, ['sort_order' => 0]);

        // 简介：卡片正文就是它 —— 「写得出来却搜不到」是这页从前的缺口
        $this->get(route('operators.index', ['q' => '与众不同的简介片段']))
            ->assertOk()
            ->assertSee('阿米娅');

        // 阵营名：阵营是个可点的筛选项，拿它当关键词当然也该搜得到
        $this->get(route('operators.index', ['q' => '检索测试阵营']))
            ->assertOk()
            ->assertSee('阿米娅');
    }

    public function test_operator_faction_parameter_accepts_both_the_canonical_name_and_the_legacy_alias(): void
    {
        $faction = $this->faction('检索测试阵营');
        $character = $this->operator(['name' => '阿米娅']);
        $character->factions()->attach($faction->id, ['sort_order' => 0]);

        // 正名 faction_id（与时间线一致）与旧别名 faction 必须等价
        foreach (['faction_id', 'faction'] as $key) {
            $this->get(route('operators.index', [$key => $faction->id]))
                ->assertOk()
                ->assertSee('阿米娅');
        }
    }

    // ---------------------------------------------------------------- HTTP 层：其他页也同一套

    public function test_dictionary_pages_share_the_same_keyword_rules(): void
    {
        Race::create([
            'name' => '测试种族',
            'slug' => 'race-search-test',
            'english' => 'TestRace',
            'description' => '测试用种族概要。',
        ]);

        // 前后空白忽略
        $this->get(route('races.index', ['q' => '  测试种族  ']))
            ->assertOk()
            ->assertSee('测试种族');

        // 大小写不敏感（英文名列）
        $this->get(route('races.index', ['q' => 'testrace']))
            ->assertOk()
            ->assertSee('测试种族');

        // 「0」不是空值：不该退化成「显示全部」
        $this->get(route('races.index', ['q' => '0']))
            ->assertOk()
            ->assertDontSee('测试种族');

        // 通配符是字面量：搜 `%` 不该把整本字典捞出来
        $this->get(route('races.index', ['q' => '%']))
            ->assertOk()
            ->assertDontSee('测试种族');
    }

    // ---------------------------------------------------------------- 清空即撤销

    /**
     * 清空检索框 = **撤销这次检索**（而不是把旧词塞回框里）。
     *
     * 这是浏览器里的交互，PHPUnit 跑不到，因此按本仓库既有的办法做**静态守卫**
     * （与时间线的 CSS/JS 守卫同理）。实现位置：`public/assets/app.js` 的
     * SidebarForms 模块 —— `resetKeyword()` + 回车/失焦两个分支。
     *
     * 三条断言各锁一件事：
     *   1. 撤销分支还在；
     *   2. 撤销时从零重建地址栏（否则分页游标 `page` 之类的会留下来）；
     *   3. 没有退回「清空后把旧词塞回输入框」的老写法 —— 那正是被修掉的行为。
     */
    public function test_clearing_the_keyword_is_wired_to_undo_the_search(): void
    {
        $js = (string) file_get_contents(public_path('assets/app.js'));

        $this->assertStringContainsString('const resetKeyword = () => {', $js, '侧栏检索没有「清空即撤销」的分支');

        $this->assertStringContainsString("target.search = '';", $js, '撤销检索时没有从零重建地址栏');

        $this->assertStringNotContainsString('input.value = initial', $js, '又退回「清空后把旧词塞回输入框」的老写法了');
    }

    /** 请求对象的最小构造：Params 只读 input，不需要走 HTTP 栈。 */
    private function request(array $query): Request
    {
        return Request::create('/', 'GET', $query);
    }

    /** 干员夹具：只给检索用得上的列，其余走默认。 */
    private function operator(array $overrides = []): Character
    {
        static $n = 0;
        $n++;

        return Character::create(array_merge([
            'name' => "检索干员{$n}",
            'slug' => "chr-search-{$n}",
            'kind' => 'operator',
            'world' => 'terra',
            'description' => '用于检索测试的简介。',
        ], $overrides));
    }
}
