<?php
/** @license MIT
 * Copyright 2018 J. King
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace MensBeam\Lax\TestCase;

use MensBeam\Lax\Url;
use MensBeam\Lax\HttpClient\HttpClient;
use MensBeam\Lax\HttpClient\Exception;
use \Psr\Http\Message\RequestInterface;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\RequestFactoryInterface;
use \Psr\Http\Client\ClientInterface;

/** @covers MensBeam\Lax\HttpClient\HttpClient */
class ParserTest extends \PHPUnit\Framework\TestCase {
    /** 
     * @dataProvider provideCodes 
     * @covers MensBeam\Lax\HttpClient\Exception
    */
    public function testCreateExceptions(string $symbol, int $exp): void {
        $exp = new Exception("httpStatus".$exp);
        $act = new Exception($symbol);
        $this->expectExceptionObject($exp);
        throw $act;
    }

    public function provideCodes(): iterable {
        // see https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
        $exceptions = [418, 419, 420, 427, 430, 451, 509];
        $cutoff = [400 => 432, 500 => 512];
        for ($a = 400; $a < $cutoff[400]; $a++) {
            $out = $a;
            if (in_array($a, $exceptions)) {
                $out = 400;
            }
            yield "httpStatus".$a => ["httpStatus".$a, $out];
        }
        for ($a = $cutoff[400]; $a < 500; $a++) {
            $out = 400;
            if (in_array($a, $exceptions)) {
                $out = $a;
            }
            yield "httpStatus".$a => ["httpStatus".$a, $out];
        }
        for ($a = 500; $a < $cutoff[500]; $a++) {
            $out = $a;
            if (in_array($a, $exceptions)) {
                $out = 500;
            }
            yield "httpStatus".$a => ["httpStatus".$a, $out];
        }
        for ($a = $cutoff[500]; $a < 600; $a++) {
            $out = 500;
            if (in_array($a, $exceptions)) {
                $out = $a;
            }
            yield "httpStatus".$a => ["httpStatus".$a, $out];
        }
        foreach ([600, 999, 1000, 2401] as $a) {
            yield "httpStatus".$a => ["httpStatus".$a, 500];
        }
        yield "httpStatus000401" => ["httpStatus000401", 401];
    }

    /** @dataProvider provideRedirections */
    public function testHandleRedirections(array $responses, int $max, ?\Throwable $exc): void {
        assert(sizeof($responses) > 0, "Test must have at least one response");
        $client = \Phake::mock(ClientInterface::class);
        $factory = \Phake::mock(RequestFactoryInterface::class);
        $req = \Phake::mock(RequestInterface::class);
        $res = \Phake::mock(ResponseInterface::class);
        $c = new HttpClient($client, $factory);
        $c->maxRedirects = $max;
        \Phake::when($client)->sendRequest->thenReturn($res);
        \Phake::when($factory)->createRequest->thenReturn($req);
        \Phake::when($req)->withUri->thenReturn($req);
        \Phake::when($req)->withMethod->thenReturn($req);
        \Phake::when($req)->getMethod->thenReturn("GET");
        $mockCode = \Phake::when($res)->getStatusCode;
        $mockLoc = \Phake::when($res)->getHeader("Location");
        $mockUrl = \Phake::when($req)->getUri;
        foreach ($responses as $url => [$code, $loc]) {
            $mockUrl = $mockUrl->thenReturn(new Url($url));
            $mockCode = $mockCode->thenReturn($code);
            $mockLoc = $mockLoc->thenReturn((array) $loc);
        }
        try {
            if ($exc) {
                $this->expectExceptionObject($exc);
                $c->sendRequest($req);
            } else {
                $this->assertSame($res, $c->sendRequest($req));    
            }
        } finally {
            \Phake::verify($client, \Phake::times(min(sizeof($responses), $max + 1)))->sendRequest($this->identicalTo($req));
            \Phake::verify($req, \Phake::times(0))->withMethod;
            $redir = 0;
            $checks = [];
            foreach ($responses as $url => [$code, $loc]) {
                if ($redir) {
                    $checks[] = \Phake::verify($req)->withUri(new Url($url));
                }
                if  ($redir++ > $max) {
                    break;
                }
            }
            if ($checks) {
                \Phake::inOrder(...$checks);
            }
        }
    }

    public function provideRedirections(): iterable {
        return [
            [[
                'http://example.com/' => [200, null],
            ], 0, null],
            [[
                'http://example.com/' => [302, null],
            ], 0, null],
            [[
                'http://example.com/'           => [302, "/index.html"],
                'http://example.com/index.html' => [200, null],
            ], 0, new Exception("tooManyRedirects")],
            [[
                'http://example.com/'           => [302, "/index.html"],
                'http://example.com/index.html' => [404, null],
            ], 10, new Exception("httpStatus404")],
            [[
                'http://a:b@c/d/' => [300, "/"],
                'http://c/'       => [200, null],
            ], 10, null],
            [[
                'http://a/'   => [301, ["http://[/", "/b/"]],
                'http://a/b/' => [200, null],
            ], 10, null],
            [[
                'http://a/'           => [301, ["http://[/", "/b/"]],
                'http://a/b/'         => [303, "http://example.com/"],
                'http://example.com/' => [304, "//a:b@c"]
            ], 10, null],
        ];
    }
}