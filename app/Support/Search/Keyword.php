<?php

namespace App\Support\Search;

use Illuminate\Database\Eloquent\Builder;

/**
 * 关键词检索：**全站唯一入口**。
 *
 * 这段语义原先在十个模块里各写一遍，于是长出三套转义、两种大小写口径，以及
 * 「同一个词在 A 页搜得到、在 B 页搜不到」这类没人能一眼说清的问题。
 * 现在收敛到这里，规则只有一条：**与时间线一致** ——
 *
 *   · 空值：trim 之后是空串才算「没给词」。**`"0"` 是有效关键词**，不是空值
 *     （用 `?:` 判空的写法会把它吞掉，那是 bug）。
 *   · 大小写：两边各折一次 —— 列侧 SQL `lower(coalesce(col, ''))`，词侧
 *     `mb_strtolower()`。只折一边就等于不折。
 *   · 通配符：`! % _` 三个字符一律转义，并**显式写出 `escape '!'`**。
 *     用 `\` 当转义符而不写 `escape` 是不成立的：SQLite 下没有默认转义符，
 *     `\%` 会被当成「反斜杠 + 任意字符」，转义反而成了新 bug（搜不到真正的下划线）。
 *   · 覆盖范围：**页面上给读者看的正文都要能搜到** —— 名称之外还有别名、说明，
 *     以及链出去的字典名（种族、阵营）。「看得见却搜不到」比搜不全更刺眼。
 *
 * 一处已知的环境差异（数据库层的事，本类抹不平）：SQLite 的 `lower()` 只折 ASCII，
 * MySQL 按字符集折，因此含非 ASCII 大写的词在测试库与生产库上表现不同。
 * ASCII 大小写在两边都正确；中日韩文字无大小写，不受影响。
 */
final class Keyword
{
    /**
     * 归一化：trim 之后非空才算有词。
     *
     * `"0"` 保留 —— 它是个合法的关键词（人物名叫「0」、编号是 0 都得搜得到）。
     * 这正是 `trim(...) ?: null` 那种写法的错处：「0」在 PHP 里是假值，会被静默丢掉。
     */
    public static function normalize(mixed $raw): ?string
    {
        if (! is_string($raw) && ! is_numeric($raw)) {
            return null;
        }

        $term = trim((string) $raw);

        return $term === '' ? null : $term;
    }

    /**
     * 给查询挂上关键词：命中所列任一列、或所列关联的任一列即可。
     *
     * 整个条件包在一个 `where(function …)` 里 —— 它是**一个** AND 项，
     * 不会把外层的其他筛选（世界、阵营、分页）冲散。
     *
     * @param  list<string>  $columns  本表的列（null 会自动按空串比）
     * @param  array<string, list<string>>  $relations  关联名 => 该关联上要搜的列
     */
    public static function apply(Builder $query, mixed $term, array $columns, array $relations = []): Builder
    {
        $term = self::normalize($term);

        if ($term === null) {
            return $query;
        }

        $needle = '%'.self::escape($term).'%';

        return $query->where(function (Builder $inner) use ($columns, $relations, $needle) {
            foreach ($columns as $column) {
                $inner->orWhereRaw(self::expression($column)." like ? escape '!'", [$needle]);
            }

            foreach ($relations as $relation => $relationColumns) {
                $inner->orWhereHas($relation, function (Builder $related) use ($relationColumns, $needle) {
                    $related->where(function (Builder $q) use ($relationColumns, $needle) {
                        foreach ($relationColumns as $column) {
                            $q->orWhereRaw(self::expression($column)." like ? escape '!'", [$needle]);
                        }
                    });
                });
            }
        });
    }

    /**
     * 词侧折叠 + 转义。与列侧的 SQL `lower()` 成对出现 —— 两边都折才比得起来。
     */
    public static function fold(string $term): string
    {
        return mb_strtolower(self::escape($term));
    }

    /**
     * PHP 侧的字面包含（大小写不敏感）—— 给**没法下推到 SQL** 的地方用。
     *
     * 目前只有地名页：别名是 JSON 列，各驱动取法不一，只能整行取出来在 PHP 里比
     * （见 PlaceController::visibleIds）。语义与 SQL 那条路对齐：同样先归一化、
     * 同样大小写不敏感、同样按字面比 —— `%` 与 `_` 在 PHP 里本来就是普通字符，
     * 不存在通配符问题，但「没给词就不筛」这条要一致。
     */
    public static function contains(?string $haystack, mixed $needle): bool
    {
        $needle = self::normalize($needle);

        if ($needle === null) {
            return true;
        }

        return mb_stripos((string) $haystack, $needle) !== false;
    }

    /**
     * 转义 LIKE 的通配符。
     *
     * 转义符选 `!` 而非 `\`：`!` 在 MySQL 与 SQLite 的字符串字面量里都没有特殊含义，
     * `escape '!'` 两个方言都能照写；`\` 在 MySQL 里要先写成 `\\` 才轮到它当转义符，
     * 而 SQLite 根本不认默认转义符 —— 两边必有一边错。
     */
    public static function escape(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }

    /** 列表达式：null 一律按空串参与比较，免得 coalesce 在十处各写各的。 */
    private static function expression(string $column): string
    {
        return "lower(coalesce({$column}, ''))";
    }
}
