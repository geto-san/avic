<?php
declare(strict_types=1);

namespace Avic\Tests\Support;

use RuntimeException;

/**
 * Runs the real app under PHP's built-in web server for the feature tests —
 * the endpoints are scripts that exit(), so the only honest way to test them
 * is over HTTP. One server per PHPUnit run, on a free port, with its own
 * upload folder, stopped when PHPUnit exits.
 *
 * Several workers are used when `setsid` is available so the race tests can
 * really send requests at the same time; otherwise it is single-threaded.
 */
final class TestServer
{
    /** @var resource|null */
    private static $process = null;
    private static ?string $base = null;
    private static string $log = '';
    private static string $uploadDir = '';
    private static bool $concurrent = false;

    public static function start(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }
        $root = dirname(__DIR__, 2);
        self::$uploadDir = sys_get_temp_dir() . '/avic-test-uploads-' . getmypid();
        self::$log = sys_get_temp_dir() . '/avic-test-server-' . getmypid() . '.log';
        @mkdir(self::$uploadDir, 0775, true);
        file_put_contents(self::$log, '');

        $probe = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($probe === false) {
            throw new RuntimeException("Cannot find a free port: $errstr");
        }
        $port = (int)substr((string)strrchr((string)stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        $env = getenv();
        $env['AVIC_UPLOAD_DIR'] = self::$uploadDir;
        $env['AVIC_ALLOW_INSECURE_COOKIES'] = '1';   // the test server is plain http
        $env['AVIC_ENV'] = 'testing';

        self::$concurrent = is_executable('/usr/bin/setsid') || is_executable('/bin/setsid');
        $command = [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root];
        if (self::$concurrent) {
            $env['PHP_CLI_SERVER_WORKERS'] = '4';
            array_unshift($command, 'setsid');   // own process group, so stop() can kill the workers too
        }

        self::$process = proc_open(
            $command,
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', self::$log, 'a'], 2 => ['file', self::$log, 'a']],
            $pipes,
            $root,
            $env
        );
        if (!is_resource(self::$process)) {
            throw new RuntimeException('Could not start the PHP test server.');
        }
        register_shutdown_function([self::class, 'stop']);

        for ($i = 0; $i < 80; $i++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.25);
            if ($socket !== false) {
                fclose($socket);
                return self::$base = "http://127.0.0.1:$port";
            }
            usleep(100000);
        }
        $log = self::log();
        self::stop();
        throw new RuntimeException("The PHP test server did not start.\n$log");
    }

    public static function stop(): void
    {
        if (is_resource(self::$process)) {
            $pid = (int)(proc_get_status(self::$process)['pid'] ?? 0);
            if ($pid > 0 && self::$concurrent) {
                @exec('kill -TERM -- -' . $pid . ' 2>/dev/null');   // the whole group: master + workers
            }
            @proc_terminate(self::$process);
            @proc_close(self::$process);
            self::$process = null;
        }
        if (self::$uploadDir !== '' && is_dir(self::$uploadDir)) {
            self::clearUploads();
            @rmdir(self::$uploadDir);
        }
        if (self::$log !== '') {
            @unlink(self::$log);
        }
        self::$base = null;
    }

    public static function concurrent(): bool
    {
        return self::$concurrent;
    }

    public static function uploadDir(): string
    {
        return self::$uploadDir;
    }

    public static function clearUploads(): void
    {
        foreach (glob(self::$uploadDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
    }

    public static function log(): string
    {
        return self::$log !== '' && is_file(self::$log) ? (string)file_get_contents(self::$log) : '';
    }
}
