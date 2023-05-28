<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use OFFLINE\Boxes\Models\Page;
use Schema;

/**
 * Reset theme for system pages.
 */
class ResetThemeForSystemPages extends Migration
{
    public function up()
    {
        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->string('theme')->nullable()->change();
        });

        if (Schema::hasColumn('offline_boxes_pages', 'is_system_entry')) {
            Page::withoutGlobalScopes()->where('is_system_entry', '=', 1)->get()->each->update(['theme' => null]);
        }
    }

    public function down()
    {
    }
}
