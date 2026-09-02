<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Feature\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Vatly\Laravel\Tests\BaseTestCase;

class CancellationReasonMigrationTest extends BaseTestCase
{
    private const STUB_PATH = __DIR__.'/../../../database/migrations/add_cancellation_reason_to_vatly_subscriptions_table.php.stub';

    protected function defineDatabaseMigrations(): void
    {
        // Skip the fixture migrations so we own the table shape here.
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('vatly_subscriptions');
    }

    public function test_it_adds_the_column_to_an_existing_table(): void
    {
        $this->createSubscriptionsTableWithout('cancellation_reason');

        $this->migrateUp();

        $this->assertTrue(Schema::hasColumn('vatly_subscriptions', 'cancellation_reason'));
    }

    public function test_running_up_twice_does_not_throw(): void
    {
        $this->createSubscriptionsTableWithout('cancellation_reason');

        $this->migrateUp();
        $this->migrateUp();

        $this->assertTrue(Schema::hasColumn('vatly_subscriptions', 'cancellation_reason'));
    }

    public function test_up_is_a_noop_when_the_column_already_exists(): void
    {
        $this->createSubscriptionsTableWithout('cancellation_reason');
        Schema::table('vatly_subscriptions', function (Blueprint $table) {
            $table->string('cancellation_reason')->nullable();
        });

        $this->migrateUp();

        $this->assertTrue(Schema::hasColumn('vatly_subscriptions', 'cancellation_reason'));
    }

    public function test_up_is_a_noop_when_the_table_does_not_exist(): void
    {
        // The table is intentionally absent (a fresh install's create migration
        // may run after this one); up() must simply skip rather than throw.
        $this->migrateUp();

        $this->assertFalse(Schema::hasTable('vatly_subscriptions'));
    }

    public function test_down_drops_the_column(): void
    {
        $this->createSubscriptionsTableWithout('cancellation_reason');

        $this->migrateUp();
        $this->migrateDown();

        $this->assertFalse(Schema::hasColumn('vatly_subscriptions', 'cancellation_reason'));

        // Idempotent: dropping again is a no-op.
        $this->migrateDown();
        $this->assertFalse(Schema::hasColumn('vatly_subscriptions', 'cancellation_reason'));
    }

    private function createSubscriptionsTableWithout(string $absentColumn): void
    {
        Schema::create('vatly_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('vatly_id')->unique();
            $table->timestamp('ends_at')->nullable();
        });

        $this->assertFalse(Schema::hasColumn('vatly_subscriptions', $absentColumn));
    }

    private function migrateUp(): void
    {
        (require self::STUB_PATH)->up();
    }

    private function migrateDown(): void
    {
        (require self::STUB_PATH)->down();
    }
}
