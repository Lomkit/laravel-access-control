<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Creates the users table with columns for user information and preferences.
     *
     * This migration defines the structure of the 'users' table, including a primary key,
     * fields for the user's name, a unique email with an optional verification timestamp,
     * password storage, and boolean flags (should_shared, should_global, should_client, should_own)
     * for additional user settings. It also adds a remember token column for session persistence
     * and timestamp columns for tracking record creation and updates.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('should_shared');
            $table->boolean('should_global');
            $table->boolean('should_client');
            $table->boolean('should_own');
            $table->rememberToken();
            $table->timestamps();
        });
    }
};
