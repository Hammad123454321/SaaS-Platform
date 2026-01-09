<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('todos')) {
            // Table exists, alter it to match the new structure
            Schema::table('todos', function (Blueprint $table) {
                // Drop old user_id foreign key if it exists
                if (Schema::hasColumn('todos', 'user_id')) {
                    $table->dropForeign('todos_user_id_foreign');
                    $table->dropColumn('user_id');
                }
                // Add missing columns
                if (!Schema::hasColumn('todos', 'priority')) {
                    $table->string('priority')->after('description');
                }
                if (!Schema::hasColumn('todos', 'creator_id')) {
                    $table->unsignedBigInteger('creator_id')->after('is_completed');
                }
                if (!Schema::hasColumn('todos', 'creator_type')) {
                    $table->string('creator_type')->after('creator_id');
                }
            });
        } else {
            // Table doesn't exist, create it
            Schema::create('todos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('workspace_id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('priority');
                $table->boolean('is_completed')->default(false);
                $table->unsignedBigInteger('creator_id');
                $table->string('creator_type'); // Polymorphic relationship

                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('todos');
    }
};
