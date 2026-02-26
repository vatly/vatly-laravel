<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Vatly\Contracts\BillableInterface;
use Vatly\Laravel\Billable;

class User extends Authenticatable implements BillableInterface
{
    use Billable;
    use HasFactory;

    protected $guarded = [];

    public function getKey(): string|int
    {
        return parent::getKey();
    }

    public function getMorphClass(): string
    {
        return parent::getMorphClass();
    }

    public function save(array $options = []): void
    {
        parent::save($options);
    }

    protected static function newFactory()
    {
        return UserFactory::new();
    }
}

class UserFactory extends \Illuminate\Database\Eloquent\Factories\Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => 'Test User',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'vatly_id' => null,
        ];
    }
}
