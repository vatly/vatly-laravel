<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Feature\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Vatly\Laravel\Tests\BaseTestCase;

class OrderLineSubscriptionIdMigrationTest extends BaseTestCase
{
    private const STUB_PATH = __DIR__.'/../../../database/migrations/add_subscription_id_to_vatly_order_lines_table.php.stub';

    protected function defineDatabaseMigrations(): void
    {
        // Skip the fixture migrations so we own the table shape here.
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('vatly_order_lines');
    }

    public function test_it_adds_the_column_to_an_existing_table(): void
    {
        $this->createOrderLinesTableWithout('subscription_id');

        $this->migrateUp();

        $this->assertTrue(Schema::hasColumn('vatly_order_lines', 'subscription_id'));
        $this->assertTrue(Schema::hasIndex('vatly_order_lines', ['subscription_id']));
    }

    public function test_running_up_twice_does_not_throw(): void
    {
        $this->createOrderLinesTableWithout('subscription_id');

        $this->migrateUp();
        $this->migrateUp();

        $this->assertTrue(Schema::hasColumn('vatly_order_lines', 'subscription_id'));
    }

    public function test_up_is_a_noop_when_the_column_already_exists(): void
    {
        $this->createOrderLinesTableWithout('subscription_id');
        Schema::table('vatly_order_lines', function (Blueprint $table) {
            $table->string('subscription_id')->nullable();
        });

        $this->migrateUp();

        $this->assertTrue(Schema::hasColumn('vatly_order_lines', 'subscription_id'));
    }

    public function test_up_is_a_noop_when_the_table_does_not_exist(): void
    {
        // The table is intentionally absent (a fresh install's create migration
        // may run after this one); up() must simply skip rather than throw.
        $this->migrateUp();

        $this->assertFalse(Schema::hasTable('vatly_order_lines'));
    }

    public function test_down_drops_the_column(): void
    {
        $this->createOrderLinesTableWithout('subscription_id');

        $this->migrateUp();
        $this->migrateDown();

        $this->assertFalse(Schema::hasColumn('vatly_order_lines', 'subscription_id'));

        // Idempotent: dropping again is a no-op.
        $this->migrateDown();
        $this->assertFalse(Schema::hasColumn('vatly_order_lines', 'subscription_id'));
    }

    private function createOrderLinesTableWithout(string $absentColumn): void
    {
        Schema::create('vatly_order_lines', function (Blueprint $table) {
            $table->id();
            $table->string('vatly_id')->unique();
            $table->string('product_type')->nullable();
            $table->string('product_id')->nullable();
        });

        $this->assertFalse(Schema::hasColumn('vatly_order_lines', $absentColumn));
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
