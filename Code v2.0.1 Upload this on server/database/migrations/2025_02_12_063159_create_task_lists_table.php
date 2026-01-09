<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Name of the task list
            $table->foreignId('project_id')->constrained()->onDelete('cascade'); // Reference to the project

            $table->timestamps();
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('task_list_id')->nullable()->constrained('task_lists')->onDelete('set null'); // Link to task list
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_lists');
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign('tasks_task_list_id_foreign');
            $table->dropColumn('task_list_id');
        });
    }
};