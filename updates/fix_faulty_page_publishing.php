<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * The Page Publishing feature was released to the
 * October Marketplace by accident. It will be released as version 3.0
 * This Migration reverts the change for 2.0 installations.
 */
class FixFaultyPagePublishing extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('offline_boxes_pages', 'published_state')) {
            return;
        }

        OFFLINE\Boxes\Models\Page::query()
            ->where('published_state', '<>', 'draft')
            ->get()
            ->each
            ->delete();

        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->dropColumn('origin_page_id');
            $table->dropColumn('published_state');
            $table->dropColumn('published_at');
            $table->dropColumn('published_by');
            $table->dropColumn('updated_by');
        });
    }

    public function down()
    {
        // Do nothing.
    }
}
