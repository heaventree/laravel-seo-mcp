<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_mcp_oauth_clients', function (Blueprint $table) {
            $table->string('client_id', 80)->primary();
            $table->string('client_name', 255)->default('');
            $table->text('redirect_uris')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('seo_mcp_oauth_auth_codes', function (Blueprint $table) {
            $table->char('code_hash', 64)->primary();
            $table->string('client_id', 80);
            $table->unsignedBigInteger('user_id');
            $table->text('redirect_uri')->nullable();
            $table->string('code_challenge', 255)->default('');
            $table->string('code_challenge_method', 10)->default('');
            $table->string('scope', 255)->default('');
            $table->timestamp('created_at');
            $table->timestamp('expires_at');
        });

        Schema::create('seo_mcp_oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->string('token_type', 10);
            $table->string('client_id', 80);
            $table->unsignedBigInteger('user_id');
            $table->string('scope', 255)->default('');
            $table->timestamp('created_at');
            $table->timestamp('expires_at');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_mcp_oauth_tokens');
        Schema::dropIfExists('seo_mcp_oauth_auth_codes');
        Schema::dropIfExists('seo_mcp_oauth_clients');
    }
};
