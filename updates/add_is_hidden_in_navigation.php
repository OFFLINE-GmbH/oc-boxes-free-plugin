<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use Schema;

/**
 * Add `is_hidden_in_navigation` feature.
 */
class AddIsHiddenInNavigation extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('offline_boxes_pages', 'is_hidden_in_navigation')) {
            return;
        }

        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->boolean('is_hidden_in_navigation')->default(false);
        });
    }

    public function down()
    {
        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->dropColumn('is_hidden_in_navigation');
        });
    }
}
