<?php

namespace Dybasedev\LunaPrototype\Tests\Unit\DnW;

use Illuminate\Database\Eloquent\Model;
use Dybasedev\LunaPrototype\Foundation\SessionHolder;

/**
 * Base test user model for DnW tests
 */
class TestUserModel extends Model implements SessionHolder
{
    protected $table = 'test_users';
    protected $fillable = ['id', 'name'];
    public $timestamps = false;
    
    /**
     * Get the class name for polymorphic relations.
     */
    public function getMorphClass()
    {
        return 'test_user';
    }
    
    public function getOperatorId(): int
    {
        return $this->id;
    }
    
    public function getOperatorType(): int
    {
        return hash_code('test_user');
    }
    
    public function getOperatorTypeName(): string
    {
        return 'test_user';
    }
    
    public function getSessionHolderContext(): ?array
    {
        return ['name' => $this->name ?? 'Test User'];
    }
}

/**
 * Helper to create test users table
 */
function createTestUsersTable(): void
{
    \Illuminate\Support\Facades\Schema::create('test_users', function ($table) {
        $table->bigIncrements('id');
        $table->string('name')->default('Test User');
    });
}