<?php

declare(strict_types=1);

namespace Vatly\Laravel\Tests\Feature\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Vatly\Laravel\Tests\BaseTestCase;

class RefundMetadataMigrationTest extends BaseTestCase
{
    private const STUB_PATH = __DIR__.'/../../../database/migrations/add_metadata_to_vatly_refunds_table.php.stub';

    protected function defineDatabaseMigrations(): void
    {
        // Skip the fixture migrations so we own the table shape here.
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('vatly_refunds');
    }

    public function test_it_adds_the_column_to_an_existing_table(): void
    {
        $this->createRefundsTableWithout('metadata');

        $this->migrateUp();

        $this->assertTrue(Schema::hasColumn('vatly_refunds', 'metadata'));
    }

    public function test_running_up_twice_does_not_throw(): void
    {
        $this->createRefundsTableWithout('metadata');

        $this->migrateUp();
        $this->migrateUp();

        $this->assertTrue(Schema::hasColumn('vatly_refunds', 'metadata'));
    }

    public function test_up_is_a_noop_when_the_column_already_exists(): void
    {
        $this->createRefundsTableWithout('metadata');
        Schema::table('vatly_refunds', function (Blueprint $table) {
            $table->json('metadata')->nullable();
        });

        $this->migrateUp();

        $this->assertTrue(Schema::hasColumn('vatly_refunds', 'metadata'));
    }

    public function test_up_is_a_noop_when_the_table_does_not_exist(): void
    {
        // The table is intentionally absent (a fresh install's create migration
        // may run after this one); up() must simply skip rather than throw.
        $this->migrateUp();

        $this->assertFalse(Schema::hasTable('vatly_refunds'));
    }

    public function test_down_drops_the_column(): void
    {
        $this->createRefundsTableWithout('metadata');

        $this->migrateUp();
        $this->migrateDown();

        $this->assertFalse(Schema::hasColumn('vatly_refunds', 'metadata'));

        // Idempotent: dropping again is a no-op.
        $this->migrateDown();
        $this->assertFalse(Schema::hasColumn('vatly_refunds', 'metadata'));
    }

    private function createRefundsTableWithout(string $absentColumn): void
    {
        Schema::create('vatly_refunds', function (Blueprint $table) {
            $table->id();
            $table->string('vatly_id')->unique();
            $table->string('customer_id')->nullable();
        });

        $this->assertFalse(Schema::hasColumn('vatly_refunds', $absentColumn));
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
