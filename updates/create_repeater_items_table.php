<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * CreateRepeaterItemsTable Migration
 */
class CreateRepeaterItemsTable extends Migration
{
    public function up()
    {
        Schema::create('offline_boxes_repeater_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('parent_id')->unsigned()->nullable()->index();
            $table->integer('sort_order')->unsigned()->nullable()->index();
            $table->mediumText('value')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('offline_boxes_repeater_items');
    }
}
