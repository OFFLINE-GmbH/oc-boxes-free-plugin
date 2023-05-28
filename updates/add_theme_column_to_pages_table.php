<?php

namespace OFFLINE\Boxes\Updates;

use Cms\Classes\Theme as CmsTheme;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use OFFLINE\Boxes\Models\Page;
use Schema;

/**
 * Add theme column to pages table.
 */
class AddThemeColumnToPagesTable extends Migration
{
    public function up()
    {
        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->string('theme')->after('layout')->nullable();
        });

        $theme = CmsTheme::getActiveTheme();

        if ($theme) {
            Page::withoutGlobalScopes()->get()->each->update(['theme' => $theme->getId()]);
        }
    }

    public function down()
    {
        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
}
