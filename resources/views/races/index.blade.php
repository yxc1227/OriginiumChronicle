@extends('layouts.app')

@section('title', '种族 · 源石纪年')
@section('page', 'races')

@section('content')
    {{-- ============================ 检索与筛选侧栏 ============================ --}}
    {{-- 种族是共享维度、不分世界，侧栏因此只有关键词检索，没有切换器 --}}
    <aside class="sidebar">
        <form method="GET" action="{{ route('races.index') }}" class="sidebar__form">
            <x-filter-head :reset-url="route('races.index')"
                           placeholder="搜索名称 / 英文名 / 说明"
                           :q="$filters['q']"/>
        </form>
    </aside>

    {{-- ============================ 种族主栏 ============================ --}}
    <main class="main">
        <div class="panel">
            <div class="panel__title">
                <span data-en="Races">种族</span>
                {{-- 后半段是事实边界：有图的种族数。让读者看到比例，比让他自己数卡片靠谱 --}}
                <span class="faint small mono">
                    CNT {{ str_pad((string) $races->count(), 2, '0', STR_PAD_LEFT) }}
                    / ART {{ str_pad((string) $illustrated, 2, '0', STR_PAD_LEFT) }}
                </span>
            </div>

            <div class="alert alert--info small">
                种族是出自《大地巡旅》第四章「泰拉种族」的字典，人物挂在它上面。
                它也是<strong>共享维度</strong> —— 同一种族可以出现在两个世界的历史里，
                因此这一页没有世界切换器，人物数是两个世界的合计。
                <br>
                书里未单独立目的写法（人物数据里实际用到、但书中没有专门词条的那些）
                只登记名称与人物归属，描述留空 —— <strong>宁可缺失也不要写错</strong>。
                <br>
                卡片上的图版是<strong>替图</strong>：书里那张种族插图（一页一个种族，全身立绘加身高标尺）
                没有可用的数字版，这里改用本仓库已有的干员立绘示意 ——
                是哪位干员、哪个版本都写在图下，点名字可以进他的简介页。
                当然，一个个体的样子代表不了一整个种族，看到「示意」两个字就该按替图读。
                @if ($races->count() > $illustrated)
                    {{-- 数出来的，不是写死的：这一页的每个数字都应当能追到数据 --}}
                    当前列出的 {{ $races->count() }} 个种族里有 {{ $races->count() - $illustrated }} 个
                    名下没有带立绘的人物，它们没有图版。
                @endif
            </div>

            @if ($races->isEmpty())
                <div class="empty">
                    @if ($filters['q'])
                        {{-- 「没搜到」与「还没收录」要分开说：前者该换关键词，后者是语料的缺口 --}}
                        没有符合筛选条件的种族。<br>
                        <span class="small">换个关键词，或点侧栏「重置」看全部。</span>
                    @else
                        尚未收录任何种族。
                    @endif
                </div>
            @endif
        </div>

        {{--
            与干员简介同一套块式：卡片网格（磨砂玻璃 + 入场错峰都随 .operator-card 自动生效）。
            卡片必须放在 .panel 之外 —— 面板是不透明底，卡片浮在它上面时玻璃就透不出背后的光。
            种族没有徽记也没有详情页：头部从名字开始，说明直接全文展示（它本身就是内容）。
        --}}
        <div class="operator-grid">
            @foreach ($races as $race)
                @php $art = $race->illustrationSplash(); @endphp

                <article class="operator-card" id="race-{{ $race->slug }}">
                    <header class="operator-card__head">
                        <div class="operator-card__id">
                            <div style="min-width:0">
                                <strong class="operator-card__name">{{ $race->name }}</strong>
                                @if (filled($race->english))
                                    <div class="operator-card__code mono">{{ $race->english }}</div>
                                @endif
                            </div>
                        </div>
                    </header>

                    {{--
                        示意图版。

                        放的**不是**书里那张种族插图，而是本仓库某位干员的立绘 —— 图源与
                        「为什么用替图」「谁怎么挑的」都写在 App\Support\RaceIllustrations 里。
                        因为是替图，图下必须点名是谁；名字链到他的简介页，读者可以顺着追问
                        「凭什么是他」。版本（精英1 / 精英2）一并写出来，免得读者以为是随机挑的一张。

                        取哪一张由模型的 illustrationSplash() 决定（优先精英二），视图不管挑选规则。
                    --}}
                    @if ($art)
                        <figure class="race-art">
                            {{-- 名字就在下面那行图注里，alt 留空免得读屏把同一个名字念两遍 --}}
                            <div class="race-art__stage">
                                <img src="{{ $art['url'] }}" alt="" loading="lazy">
                            </div>
                            <figcaption class="race-art__caption">示意 ·
                                <a class="race-art__who" href="{{ route('operators.show', $race->illustration) }}">{{ $race->illustration->name }}</a>
                                <span class="mono faint">{{ $art['label'] }}</span>
                            </figcaption>
                        </figure>
                    @else
                        {{--
                            缺口种族照样占一块**同样大小**的图版。
                            网格里留一块空白会被读成「图没加载出来」；写一行说明、配一层站内
                            那套 45° 斜纹，才是如实的空（与徽记的「没有就不出现」并不矛盾：
                            徽记嵌在一行文字里，空位会成为文字的缺口；这里是一整块图版，
                            空着会让整张卡片的高度塌下去）。
                        --}}
                        <figure class="race-art">
                            <div class="race-art__stage race-art__stage--none">
                                <span class="mono faint">NO ART</span>
                            </div>
                            <figcaption class="race-art__caption faint">示意 · 暂无（本仓库该种族名下没有带立绘的人物）</figcaption>
                        </figure>
                    @endif

                    @if ($race->characters_count > 0)
                        <div class="chips">
                            <span class="chip">{{ $race->characters_count }} 位人物</span>
                        </div>
                    @endif

                    @if (filled($race->description))
                        <p class="operator-card__profile operator-card__profile--full">{{ $race->description }}</p>
                    @else
                        {{-- 缺口如实呈现，而不是留白：留白会让人以为「书里没有这个种族」 --}}
                        <p class="operator-card__profile operator-card__profile--full operator-card__profile--missing">
                            书里第四章未为这一种族单独立目，本仓库只登记了名称与人物归属。
                        </p>
                    @endif
                </article>
            @endforeach
        </div>
    </main>
@endsection
