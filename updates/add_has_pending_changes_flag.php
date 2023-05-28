<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use OFFLINE\Boxes\Models\BoxesSetting;
use Schema;

/**
 * Add `has_pending_changes` flag.
 */
class AddHasPendingChangesFlag extends Migration
{
    public function up()
    {
        BoxesSetting::set('revisions_cleanup_enabled', true);
        BoxesSetting::set('revisions_keep_number', 20);
        BoxesSetting::set('revisions_keep_days', 14);

        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            if (!Schema::hasColumn('offline_boxes_pages', 'has_pending_changes')) {
                $table->boolean('has_pending_changes')->default(false)->after('published_by');
            }

            if (!Schema::hasColumn('offline_boxes_pages', 'version')) {
                $table->boolean('version')->nullable()->after('has_pending_changes');
            }
        });
    }

    public function down()
    {
        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            if (Schema::hasColumn('offline_boxes_pages', 'has_pending_changes')) {
                $table->dropColumn('has_pending_changes');
            }

            if (Schema::hasColumn('offline_boxes_pages', 'version')) {
                $table->dropColumn('version');
            }
        });
    }
}
