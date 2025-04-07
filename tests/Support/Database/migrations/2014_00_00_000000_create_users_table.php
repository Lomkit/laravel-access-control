<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Create the users table.
     *
     * This migration creates the 'users' table with columns for basic user details such as name, email, and password,
     * along with a nullable timestamp for email verification. It also defines boolean flags (should_shared, should_global,
     * should_client, should_own) to capture user-specific preferences. Additionally, the table includes a remember token
     * for authentication persistence and standard timestamp columns for tracking creation and update times.
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
