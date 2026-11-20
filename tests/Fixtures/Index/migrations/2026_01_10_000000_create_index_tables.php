<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamps();
        });

        Schema::create('guide_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guide_id')->constrained();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('body');
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
