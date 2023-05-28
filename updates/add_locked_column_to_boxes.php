<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * Add locked column to boxes.
 */
class AddLockedColumnToBoxes extends Migration
{
    public function up()
    {
        Schema::table('offline_boxes_boxes', function (Blueprint $table) {
            $table->json('locked')->nullable()->after('data');

            if (Schema::hasColumn('offline_boxes_boxes', 'read_only')) {
                $table->dropColumn(['read_only']);
            }
        });
    }

    public function down()
    {
        Schema::table('offline_boxes_boxes', function (Blueprint $table) {
            if (Schema::hasColumn('offline_boxes_boxes', 'locked')) {
                $table->dropColumn(['locked']);
            }
        });
    }
}
