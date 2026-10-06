<?php

namespace App\Http\Controllers;

use App\Models\Race;
use App\Support\Search\Keyword;
use App\Support\Search\Params;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * 种族：人物挂在它上面（`characters.race_id`）。
 *
 * 种族是**共享维度** —— 同一种族可以出现在两个世界的历史里，
 * 强行按世界切分只会把同一个概念拆成两份，因此这一页没有世界切换器，
 * 人物数是两个世界的合计（页面上标注了这一点）。
 */
class RaceController extends Controller
{
    public function index(Request $request): View
    {
        // 种族不分世界，搜索同样不分世界：一份字典查两边的历史
        $keyword = Params::text($request, 'q');
        $races = $this->races($keyword);

        return view('races.index', [
            'races' => $races,
            /*
             * 有几个种族挂得上示意立绘。
             *
             * 在控制器里算而不是在视图里：卡片上那一块图是这一页最显眼的东西，
             * 而「多少种族有图」正是它的事实边界（目前 36 个里 35 个有、1 个没有）。
             * 让读者看到这个比例，比让他自己数卡片靠谱。
             */
            'illustrated' => $races->filter(fn (Race $race) => $race->hasIllustration())->count(),
            'filters' => ['q' => $keyword],
        ]);
    }

    /**
     * 按书里的排序给出，附带人物数与示意立绘所用的干员。
     *
     * 计数用 withCount，而不是逐条 `$race->characters->count()` ——
     * 后者在列表里就是 N 次查询，而列表正是最显眼的那一页。
     * 示意立绘同理走 with：卡片上要写「示意：凯尔希 · 精英2」并链到她的简介页，
     * 懒加载会让这一页多出 35 次查询。
     *
     * @return Collection<int, Race>
     */
    private function races(?string $keyword): Collection
    {
        return Keyword::apply(Race::query(), $keyword, ['name', 'english', 'description'])
            ->withCount('characters')
            ->with('illustration')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
