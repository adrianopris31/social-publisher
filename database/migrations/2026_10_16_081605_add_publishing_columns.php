<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('dispatched_at')->nullable()->index()->after('publish_at');
        });

        Schema::table('publications', function (Blueprint $table) {
            $table->string('processing_id')->nullable()->after('external_url');
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->timestamp('reconnection_required_at')->nullable()->after('avatar_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('dispatched_at');
        });

        Schema::table('publications', function (Blueprint $table) {
            $table->dropColumn('processing_id');
        });

        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn('reconnection_required_at');
        });
    }
};
