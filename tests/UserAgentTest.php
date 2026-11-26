<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests;

use Composer\InstalledVersions;
use Vatly\Fluent\Vatly;

/**
 * Sends a real request to a PHP built-in web server on 127.0.0.1 that echoes
 * the User-Agent it received (see Fixtures/user-agent-echo-server.php).
 */
class UserAgentTest extends BaseTestCase
{
    /** @var resource|null */
    private static $server;

    private static string $echoServerUrl;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        self::$server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__.'/Fixtures/user-agent-echo-server.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );
        self::$echoServerUrl = "http://127.0.0.1:{$port}";

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $port);

            if ($connection !== false) {
                fclose($connection);

                return;
            }

            usleep(50000);
        }

        self::fail('The PHP built-in web server did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }

        parent::tearDownAfterClass();
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('vatly.api_url', self::$echoServerUrl);
    }

    public function test_the_user_agent_ends_with_this_packages_name_and_installed_version(): void
    {
        $userAgent = $this->app->make(Vatly::class)->getApiClient()->performHttpCall('GET', 'user-agent')->userAgent;

        $version = ltrim((string) InstalledVersions::getPrettyVersion('vatly/vatly-laravel'), 'v');
        $this->assertNotSame('', $version);
        $this->assertStringContainsString(' PHP/'.phpversion().' Curl/* ', $userAgent);
        $this->assertStringEndsWith(' vatly/vatly-laravel/'.$version, $userAgent);
        $this->assertSame(1, substr_count($userAgent, 'vatly/vatly-laravel/'));
    }
}
