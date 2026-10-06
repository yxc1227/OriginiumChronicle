<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 种族的示意立绘。
 *
 * 《大地巡旅》第四章给每个种族配了整页插图，但那是**书里的图版**，不在本项目手上
 * （来源、边界与裁决见 `App\Support\RaceIllustrations` 的说明）。本仓库改用
 * **已在库里的干员立绘**来示意：那是同一位干员的两张立绘之一，来源可追溯、
 * 授权与站内其他图一致，页面也已经在用它。
 *
 * 与头像（`characters.avatar`）、徽记（`places.logo`）的差别只有一处，且是刻意的：
 * 那两者存**相对路径**，这里存**外键**。理由是谁来示意这件事本身就是一条
 * 「关于人的裁定」—— 页面上必须写出「示意：凯尔希 · 精英2」并把名字链到她的简介页，
 * 只存路径就只剩一张无名的图，读者既不知道那是谁，也无从追问它凭什么代表这个种族。
 *
 * 留空是常态而不是缺陷：目前 36 个种族里有 1 个（温迪戈）在本仓库只有一位
 * 没有立绘的人物，页面据此不渲染图版，而不是渲染一张碎图。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->foreignId('illustration_id')->nullable()->after('description')
                ->constrained('characters')->nullOnDelete()
                ->comment('示意立绘所用的干员（外键；留空表示本仓库暂无可用立绘）');
        });
    }

    public function down(): void
    {
        Schema::table('races', function (Blueprint $table) {
            $table->dropConstrainedForeignId('illustration_id');
        });
    }
};
