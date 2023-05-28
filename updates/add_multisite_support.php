<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use OFFLINE\Boxes\Models\Page;
use Schema;
use Site;

/**
 * Add multisite support.
 */
class AddMultisiteSupport extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('offline_boxes_pages', 'site_id')) {
            Schema::table('offline_boxes_pages', function (Blueprint $table) {
                $table->integer('site_id')->nullable()->index();
                $table->integer('site_root_id')->nullable()->index();
            });
        }

        Page::whereNull('site_id')->update(['site_id' => Site::getPrimarySite()->id]);
    }

    public function down()
    {
        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->dropColumn('site_id');
            $table->dropColumn('site_root_id');
        });
    }
}
