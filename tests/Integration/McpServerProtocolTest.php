<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/** Starts the real STDIO server, so broken attributes or registry filters show up here. */
final class McpServerProtocolTest extends TestCase
{
    public function testServerListsToolsPromptsAndResources(): void
    {
        $in = new InputStream();
        $p = new Process([\PHP_BINARY, 'bin/console', 'mcp:server', '--env=test'], \dirname(__DIR__, 2), null, $in, 20);
        $p->start();

        $send = static fn (array $m) => $in->write(json_encode(['jsonrpc' => '2.0'] + $m, \JSON_THROW_ON_ERROR)."\n");
        $send(['id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass(), 'clientInfo' => ['name' => 'phpunit', 'version' => '1']]]);
        $send(['method' => 'notifications/initialized']);
        $send(['id' => 2, 'method' => 'tools/list']);
        $send(['id' => 3, 'method' => 'prompts/list']);
        $send(['id' => 4, 'method' => 'resources/templates/list']);

        $deadline = microtime(true) + 15;
        while (microtime(true) < $deadline && substr_count($p->getOutput(), '"id":4') < 1) {
            usleep(100_000);
        }
        $in->close();
        $p->stop(1);
        $out = $p->getOutput();

        foreach (['check_week', 'precheck_draft', 'confirm_verdict', 'override_verdict', 'rate_meeting', 'weekly-review', 'draft-check', 'invite-verdict'] as $expected) {
            self::assertStringContainsString($expected, $out, sprintf('MCP server does not expose "%s". stderr: %s', $expected, $p->getErrorOutput()));
        }
    }
}
