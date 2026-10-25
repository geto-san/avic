<?php
declare(strict_types=1);

namespace Avic\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/config/api/upload-files.php';

final class UploadFilesTest extends TestCase
{
    /** @var string|false */
    private $savedEnv;
    private string $dir;
    /** @var list<string> */
    private array $cleanup = [];

    protected function setUp(): void
    {
        $this->savedEnv = getenv('AVIC_UPLOAD_DIR');
        $this->dir = sys_get_temp_dir() . '/avic-upl-' . uniqid();
        mkdir($this->dir);
        putenv('AVIC_UPLOAD_DIR=' . $this->dir);
    }

    protected function tearDown(): void
    {
        putenv($this->savedEnv === false ? 'AVIC_UPLOAD_DIR' : 'AVIC_UPLOAD_DIR=' . $this->savedEnv);
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
        foreach ($this->cleanup as $path) {
            is_dir($path) ? @rmdir($path) : @unlink($path);
        }
    }

    public function testEnvironmentVariableChoosesTheFolder(): void
    {
        $this->assertSame($this->dir, uploadDir());
        putenv('AVIC_UPLOAD_DIR=' . $this->dir . '/');
        $this->assertSame($this->dir, uploadDir(), 'trailing slash is trimmed');
    }

    public function testDefaultFolderIsOutsideTheProject(): void
    {
        putenv('AVIC_UPLOAD_DIR');
        $project = dirname(__DIR__, 2);
        $this->assertSame(dirname($project) . '/avic-uploads', uploadDir());
        $this->assertStringStartsNotWith($project . '/', uploadDir() . '/');
    }

    public function testFindsAFileInTheUploadFolder(): void
    {
        file_put_contents($this->dir . '/abc123.png', 'x');
        $this->assertSame($this->dir . '/abc123.png', uploadResolvePath('abc123.png'));
    }

    public function testOldStylePathsStillResolveByName(): void
    {
        file_put_contents($this->dir . '/abc123.png', 'x');
        $this->assertSame($this->dir . '/abc123.png', uploadResolvePath('uploads/abc123.png'));
    }

    public function testFallsBackToTheLegacyProjectFolder(): void
    {
        $legacy = legacyUploadDir();
        if (!is_dir($legacy)) {
            mkdir($legacy);
            $this->cleanup[] = $legacy;       // only remove it if this test created it
        }
        $name = 'legacy-' . uniqid() . '.png';
        file_put_contents("$legacy/$name", 'x');
        array_unshift($this->cleanup, "$legacy/$name");
        $this->assertSame("$legacy/$name", uploadResolvePath('uploads/' . $name));
    }

    /** @dataProvider unsafePaths */
    public function testPathsCannotEscapeTheUploadFolders(string $path): void
    {
        $this->assertNull(uploadResolvePath($path));
    }

    /** @return array<string,array{string}> */
    public static function unsafePaths(): array
    {
        return [
            'empty'            => [''],
            'dot'              => ['.'],
            'dot dot'          => ['..'],
            'traversal'        => ['../../../../etc/passwd'],
            'windows traversal' => ['..\\..\\windows\\win.ini'],
            'missing file'     => ['nope.png'],
        ];
    }

    /** @dataProvider mimeCases */
    public function testOnlyImagesAndPdfsAreAllowed(string $mime, ?string $ext): void
    {
        $this->assertSame($ext, uploadExtFromMime($mime));
    }

    /** @return array<string,array{string,?string}> */
    public static function mimeCases(): array
    {
        return [
            'jpeg' => ['image/jpeg', 'jpg'], 'png' => ['image/png', 'png'], 'webp' => ['image/webp', 'webp'],
            'pdf' => ['application/pdf', 'pdf'], 'case-insensitive' => ['IMAGE/PNG', 'png'],
            'html' => ['text/html', null], 'php' => ['text/x-php', null], 'svg (script-capable)' => ['image/svg+xml', null],
            'executable' => ['application/x-dosexec', null],
        ];
    }
}
