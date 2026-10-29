<?php
declare(strict_types=1);

/** Starts a throwaway PHP server on the test database and talks to it like a browser. */
final class TestServer
{
    private static $proc = null;
    public static string $base = '';
    public static string $uploads = '';

    public static function start(): void
    {
        if (self::$proc !== null) {
            return;
        }
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $err);
        $port = (int)explode(':', (string)stream_socket_get_name($sock, false))[1];
        fclose($sock);
        self::$uploads = sys_get_temp_dir() . '/avic-test-uploads-' . getmypid();
        $env = getenv() + [];
        $env['AVIC_UPLOAD_DIR'] = self::$uploads;
        self::$proc = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', AVIC_ROOT . '/public', AVIC_ROOT . '/public/index.php'],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'a'], 2 => ['file', '/dev/null', 'a']],
            $pipes, AVIC_ROOT, $env
        );
        self::$base = "http://127.0.0.1:$port";
        for ($i = 0; $i < 50; $i++) {
            if (@fsockopen('127.0.0.1', $port)) {
                register_shutdown_function([self::class, 'stop']);
                return;
            }
            usleep(100000);
        }
        throw new RuntimeException('test server did not start');
    }

    public static function stop(): void
    {
        if (self::$proc) {
            proc_terminate(self::$proc);
            self::$proc = null;
        }
        if (self::$uploads && is_dir(self::$uploads)) {
            array_map('unlink', glob(self::$uploads . '/*') ?: []);
            @rmdir(self::$uploads);
        }
    }
}

final class Browser
{
    private string $jar;

    public function __construct()
    {
        TestServer::start();
        $this->jar = tempnam(sys_get_temp_dir(), 'avicjar');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /** @return array{status:int,headers:array<string,string>,body:string,location:?string} */
    public function request(string $method, string $path, array|string|null $body = null, array $headers = []): array
    {
        $ch = curl_init(TestServer::$base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 15,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $h = [];
        foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $h[strtolower(trim($k))] = trim($v);
            }
        }
        return ['status' => $status, 'headers' => $h, 'body' => substr($raw, $size), 'location' => $h['location'] ?? null];
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** Follow redirects (GET) up to 5 hops. */
    public function follow(array $res): array
    {
        for ($i = 0; $i < 5 && $res['location']; $i++) {
            $res = $this->get($res['location']);
        }
        return $res;
    }

    public function token(): string
    {
        $page = $this->get('/login');
        if (!preg_match('/name="_csrf" value="([a-f0-9]+)"/', $page['body'], $m)) {
            $page = $this->get('/dashboard');
            preg_match('/name="_csrf" value="([a-f0-9]+)"/', $page['body'], $m);
        }
        return $m[1] ?? '';
    }

    /** POST with a valid CSRF token (unless $csrf is false). */
    public function post(string $path, array $fields = [], bool $csrf = true): array
    {
        if ($csrf) {
            $fields['_csrf'] = $this->token();
        }
        return $this->request('POST', $path, http_build_query($fields));
    }

    public function upload(string $path, array $fields, string $file, string $name, string $mime): array
    {
        $fields['_csrf'] = $this->token();
        $fields['file'] = new CURLFile($file, $mime, $name);
        return $this->request('POST', $path, $fields);
    }

    public function login(string $email, string $password = 'Passw0rd!'): array
    {
        return $this->post('/login', ['email' => $email, 'password' => $password]);
    }
}
