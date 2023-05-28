<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * Add custom_config column to pages table.
 */
class AddCustomConfigColumntoPagesTable extends Migration
{
    public function up()
    {
        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->text('custom_config')->nullable()->after('nest_depth');
        });
    }

    public function down()
    {
        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->dropColumn('custom_config');
        });
    }
}
