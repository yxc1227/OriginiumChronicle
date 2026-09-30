<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 种族字典（《大地巡旅》第四章「泰拉种族」）。
 *
 * 之所以把原来的自由字符串升级成字典：写错一个种族名不会有任何东西报错，
 * 而「菲林」写成「菲琳」之后，人眼看不出、筛选也筛不全。
 */
#[Fillable(['name', 'slug', 'english', 'description', 'illustration_id', 'sort_order'])]
class Race extends Model
{
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }

    /**
     * 用于示意的干员（本仓库裁定，见 {@see \App\Support\RaceIllustrations}）。
     *
     * 与 `characters.race_id` 是**两个方向不同的问题**：那条说的是「这个人属于哪一族」，
     * 这条说的是「这一族拿谁来示意」—— 后者是编辑判断，可换，且大部分种族并没有谁
     * 能真正代表它。因此它是 nullable 的外键，而不是从 characters 里算出来的。
     */
    public function illustration(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'illustration_id');
    }

    /**
     * 页面上真正要渲染的那一张图：优先精英二（立绘里信息最全的一张），
     * 没有精英二就取排序第一张（通常是精英一）。
     *
     * 挑选放在模型里而不是视图里：这是数据的性质，不是某一页的排版偏好 ——
     * 将来若有第二处要展示种族示意，选哪一张不该有第二种答案。
     *
     * @return array{key: string, label: string, url: string}|null
     */
    public function illustrationSplash(): ?array
    {
        $splashes = $this->illustration?->splashList() ?? [];

        if ($splashes === []) {
            return null;
        }

        foreach ($splashes as $splash) {
            if ($splash['key'] === '2') {
                return $splash;
            }
        }

        return $splashes[0];
    }

    /** 是否有可展示的示意立绘。视图据此整块不渲染，而不是渲染一张碎图。 */
    public function hasIllustration(): bool
    {
        return $this->illustrationSplash() !== null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'english' => $this->english,
            'description' => $this->description,
        ];
    }
}
