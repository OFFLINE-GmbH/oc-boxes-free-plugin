<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * Add read only flag to boxes.
 */
class AddReadOnlyFlagToBoxes extends Migration
{
    public function up()
    {
        Schema::table('offline_boxes_boxes', function (Blueprint $table) {
            $table->boolean('read_only')->default(0)->after('data');
        });
    }

    public function down()
    {
        Schema::table('offline_boxes_boxes', function (Blueprint $table) {
            if (Schema::hasColumn('offline_boxes_boxes', 'read_only')) {
                $table->dropColumn('read_only');
            }
        });
    }
}
