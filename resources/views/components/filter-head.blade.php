{{--
    服务端筛选侧栏的固定头，与时间线 #filters 的头部同构：标题 + 重置 + 关键词输入。
    重置用普通链接而不是按钮：服务端页的「重置」就是回到不带查询串的列表，
    没有客户端状态要清，交给 URL 才是最诚实的实现。
    search 设为 false 可省去关键词框（页面上没有任何能按关键词搜的字段时才这样做）。

    检索不放按钮（2026-09-27 改，与时间线 #filters 对齐）：触发时机由 SidebarForms 统一管 ——
    回车、或失焦时内容真的变了才提交；空值不提交（那等于「全部」，侧栏已有「重置」）。
    因此 placeholder 要把「搜的是什么」说清 —— 去掉按钮后，它是框里唯一的提示。
--}}
@props(['resetUrl', 'placeholder' => '', 'q' => null, 'search' => true])

<div class="sidebar__head">
    <div class="filter-head">
        <strong data-en="Filter">检索与筛选</strong>
        <a class="btn btn--ghost btn--sm" href="{{ $resetUrl }}"><x-icon name="reset"/>重置</a>
    </div>

    @if ($search)
        <div class="field" style="margin-bottom:0">
            <input type="search" name="q" value="{{ $q }}" placeholder="{{ $placeholder }}" data-autosubmit>
        </div>
    @endif
</div>
