<?php

namespace App\Support\Search;

use Illuminate\Http\Request;

/**
 * 筛选参数的**唯一读法**。
 *
 * 从前每个控制器各读各的：`trim(...) ?: null`、`$request->integer(...) ?: null`、
 * `filled(...)` 三种写法混着用，于是「同一个参数在 A 页能留空、在 B 页会报错」
 * 这类差异只能靠逐页读代码才发现。
 *
 * 这里的规矩与时间线一致，且只与时间线一致：
 *   · 文本＝{@see Keyword::normalize()}（trim 后为空才算空；`"0"` 保留）；
 *   · id＝**正整数**才认，空串 / 0 / 负数 / 数组 / 非数字一律当「没选」；
 *   · 多值 id＝去重后的正整数列表，一个都不剩时归 null（而不是空数组 —— 空数组
 *     在控制器里要额外判一次，容易漏）。
 *
 * 参数名沿用时间线的习惯：指代实体的用 `*_id`（`faction_id` / `place_id` /
 * `tag_ids`），`Params::id()` 接受多个键名，正是为了给旧链接留一条别名通路。
 */
final class Params
{
    /** 文本：与 Keyword 同一条规矩。 */
    public static function text(Request $request, string $key): ?string
    {
        return Keyword::normalize($request->input($key));
    }

    /** 单值 id：按给定顺序找第一个合法值 —— 前面的键名是正名，后面的是别名。 */
    public static function id(Request $request, string ...$keys): ?int
    {
        foreach ($keys as $key) {
            $value = $request->input($key);

            if (is_array($value)) {
                continue;
            }

            $value = is_string($value) ? trim($value) : $value;

            if (! is_numeric($value)) {
                continue;
            }

            $id = (int) $value;

            if ($id > 0) {
                return $id;
            }
        }

        return null;
    }

    /** 多值 id：`?tag_ids[]=1&tag_ids[]=2`；单值写法也收（`?tag_ids=1`）。 */
    public static function ids(Request $request, string $key): ?array
    {
        $values = $request->input($key);

        if (! is_array($values)) {
            $single = self::id($request, $key);

            return $single === null ? null : [$single];
        }

        $ids = [];

        foreach ($values as $value) {
            if (is_array($value)) {
                continue;
            }

            $value = is_string($value) ? trim($value) : $value;

            if (! is_numeric($value)) {
                continue;
            }

            $id = (int) $value;

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return $ids === [] ? null : array_values($ids);
    }

    /** 开关：`1 / true / on / yes` 为真，其余（含空、0）为假。 */
    public static function flag(Request $request, string $key): bool
    {
        return filter_var($request->input($key), FILTER_VALIDATE_BOOL);
    }
}
