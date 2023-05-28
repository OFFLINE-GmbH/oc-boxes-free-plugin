<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * CreatePagesTable Migration
 */
class CreatePagesTable extends Migration
{
    public function up()
    {
        Schema::create('offline_boxes_pages', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('url')->nullable();
            $table->string('layout')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->boolean('is_hidden')->default(0);
            $table->integer('category_id')->nullable();

            // Nested Tree
            $table->integer('parent_id')->nullable();
            $table->integer('nest_left')->nullable();
            $table->integer('nest_right')->nullable();
            $table->integer('nest_depth')->nullable();

            $table->timestamps();
        });

        // Multisite Support
        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->integer('site_id')->nullable()->index();
            $table->integer('site_root_id')->nullable()->index();
        });
    }

    public function down()
    {
        Schema::dropIfExists('offline_boxes_pages');
    }
}
