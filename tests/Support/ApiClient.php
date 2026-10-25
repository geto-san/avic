<?php
declare(strict_types=1);

namespace Avic\Tests\Support;

use CURLFile;
use RuntimeException;

/** A browser stand-in: curl + a cookie jar, so the PHP session survives between calls. */
final class ApiClient
{
    private string $jar;

    public function __construct(private readonly string $base)
    {
        $this->jar = (string)tempnam(sys_get_temp_dir(), 'avic-jar-');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    public function login(string $email, string $password = 'Demo2026!'): Response
    {
        return $this->postJson('config/auth/login.php', ['email' => $email, 'password' => $password]);
    }

    public function get(string $path): Response
    {
        return $this->send($path, []);
    }

    /** @param array<string,mixed> $data */
    public function postJson(string $path, array $data): Response
    {
        return $this->send($path, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
    }

    /**
     * multipart/form-data, as the claim wizard sends it.
     *
     * @param array<string,scalar>                                        $fields
     * @param list<array{path:string,type:string,name:string}>            $files
     */
    public function postMultipart(string $path, array $fields, array $files = []): Response
    {
        $post = $fields;
        foreach ($files as $i => $file) {
            $post["files[$i]"] = new CURLFile($file['path'], $file['type'], $file['name']);
        }
        return $this->send($path, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post]);
    }

    /**
     * Send the same JSON POST from several signed-in clients AT THE SAME TIME (curl_multi).
     *
     * Use one client per simulated person/device: PHP serialises requests that share a
     * session (the session file is locked for the whole request), so a "race" made from
     * a single client never actually overlaps and would prove nothing about the database.
     *
     * @param list<ApiClient>     $clients
     * @param array<string,mixed> $data
     * @return list<Response> in the same order as $clients
     */
    public static function postJsonTogether(array $clients, string $path, array $data): array
    {
        $multi = curl_multi_init();
        $handles = [];
        $headers = array_fill(0, count($clients), []);
        foreach ($clients as $i => $client) {
            $curl = curl_init(rtrim($client->base, '/') . '/' . ltrim($path, '/'));
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_COOKIEFILE => $client->jar,   // read-only: no handle writes the jar
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers, $i): int {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $headers[$i][strtolower(trim($parts[0]))] = trim($parts[1]);
                    }
                    return strlen($line);
                },
            ]);
            curl_multi_add_handle($multi, $curl);
            $handles[$i] = $curl;
        }
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 0.2);
            }
        } while ($running && $status === CURLM_OK);

        $responses = [];
        foreach ($handles as $i => $curl) {
            $responses[] = new Response((int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE), (string)curl_multi_getcontent($curl), $headers[$i]);
            curl_multi_remove_handle($multi, $curl);
            curl_close($curl);
        }
        curl_multi_close($multi);
        return $responses;
    }

    /** @param array<int,mixed> $options */
    private function send(string $path, array $options): Response
    {
        $headers = [];
        $curl = curl_init(rtrim($this->base, '/') . '/' . ltrim($path, '/'));
        if ($curl === false) {
            throw new RuntimeException('curl_init failed');
        }
        curl_setopt_array($curl, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        $body = curl_exec($curl);
        if ($body === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('HTTP request failed: ' . $error . "\n" . TestServer::log());
        }
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return new Response($status, (string)$body, $headers);
    }
}
