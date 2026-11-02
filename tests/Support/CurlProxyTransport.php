<?php

namespace Tests\Support;

use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Process\Process;
use function Amp\ByteStream\buffer;

/** Live-test-only transport: honors the runner's proxy and CA trust via curl. */
final class CurlProxyTransport
{
    public function request(Request $request): Response
    {
        if ($request->getUri()->getScheme() !== 'https' || $request->getUri()->getHost() !== 'api.telegram.org') {
            throw new \LogicException('Live smoke transport only permits Telegram HTTPS.');
        }
        $escape = static fn (string $text): string => strtr($text, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r']);
        $body = buffer($request->getBody()->getContent());
        $config = 'url = "' . $escape((string) $request->getUri()) . '"' . "\n"
            . 'request = "POST"' . "\n" . 'header = "Content-Type: application/json"' . "\n"
            . 'data-binary = "' . $escape($body) . '"' . "\n";
        // The token-bearing URL is passed via stdin, never command-line arguments.
        $process = Process::start(['curl', '--silent', '--show-error', '--max-time', '25', '--write-out', "\n%{http_code}", '--config', '-']);
        $process->getStdin()->write($config); $process->getStdin()->end();
        $stdout = \Amp\async(fn () => buffer($process->getStdout()));
        $stderr = \Amp\async(fn () => buffer($process->getStderr()));
        $exit = $process->join(); $out = $stdout->await(); $stderr->await();
        if ($exit !== 0) { throw new \RuntimeException('Live HTTP transport failed; details suppressed to protect credentials.'); }
        $separator = strrpos($out, "\n");
        if ($separator === false) { throw new \RuntimeException('Missing HTTP status.'); }
        $status = (int) substr($out, $separator + 1);
        if ($status < 100 || $status > 599) { throw new \RuntimeException('Invalid HTTP status.'); }
        return new Response('1.1', $status, null, ['content-type' => 'application/json'], substr($out, 0, $separator), $request);
    }
}
