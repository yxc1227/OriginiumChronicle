<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 仓库图标：给「列表类工具」看的 logo。
 *
 * 两个位置都是**第三方工具的约定**，不是我们的发明（配方见 docs/LOGO.md §6）：
 *   · `icon.png` 放在**仓库根目录** —— Sourcetree 用它显示本地仓库的图标；
 *   · `.idea/icon.png` —— JetBrains 系列用它显示「最近项目」列表里的图标
 *     （该目录其余内容仍不入库，见 .gitignore 里的负向规则）。
 *
 * 两份都是 `public/apple-touch-icon.png` 的副本 —— 那是按 §6 由 headless Chrome
 * 原生渲染的栅格产物，只在几何真的改了才重跑。这里断言字节相同：几何改了、
 * 仓库图标忘了跟着换，就是这张网兜住的事。
 */
class LogoAssetsTest extends TestCase
{
    /** 两份副本的源头：栅格化的标记本体。 */
    private const SOURCE = 'public/apple-touch-icon.png';

    public function test_repository_root_carries_the_sourcetree_icon(): void
    {
        $this->assertIconCopy(base_path('icon.png'), 'Sourcetree 读仓库根目录的 icon.png —— 别把它挪走');
    }

    public function test_idea_carries_the_jetbrains_project_icon(): void
    {
        $this->assertIconCopy(base_path('.idea/icon.png'), 'JetBrains 读 .idea/icon.png');
    }

    /** 三个断言合一：在、是 PNG、且与源头同一份字节（方形容器由源头保证）。 */
    private function assertIconCopy(string $path, string $forWhom): void
    {
        $this->assertFileExists($path, $forWhom);

        $size = getimagesize($path);

        $this->assertNotFalse($size, "{$path} 不是浏览器/工具认得的图片");
        $this->assertSame('image/png', $size['mime'] ?? '', "{$path} 应当是 PNG（列表图标不做矢量支持）");
        $this->assertSame(
            $size[0],
            $size[1],
            "{$path} 应当是方形 —— 列表图标按方形容器显示，非方形会被拉伸",
        );

        $this->assertSame(
            file_get_contents(public_path('apple-touch-icon.png')),
            file_get_contents($path),
            "{$path} 应当是 ".self::SOURCE.' 的副本（几何改了要按 docs/LOGO.md §6 重新复制）',
        );
    }
}
