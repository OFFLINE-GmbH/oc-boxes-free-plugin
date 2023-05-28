<?php

namespace OFFLINE\Boxes\Updates;

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use OFFLINE\Boxes\Classes\PublishedState;
use OFFLINE\Boxes\Models\Box;
use OFFLINE\Boxes\Models\Page;
use Schema;

/**
 * Add page publishing feature.
 */
class PagePublishing extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('offline_boxes_pages', 'published_state')) {
            return;
        }

        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->integer('origin_page_id')->nullable()->after('site_root_id');
            $table->string('published_state')->default(PublishedState::DRAFT)->index()->after('origin_page_id');
            $table->timestamp('published_at')->nullable()->after('published_state');
            $table->integer('published_by')->nullable()->after('published_at');
            $table->integer('updated_by')->nullable()->after('updated_at');
            $table->boolean('has_pending_changes')->default(false)->after('published_by');
            $table->boolean('version')->nullable()->after('has_pending_changes');
        });

        Schema::table('offline_boxes_boxes', function (Blueprint $table) {
            $table->dropIndex('offline_boxes_boxes_unique_id_unique');

            $table->integer('origin_box_id')->nullable()->after('holder_id');
            $table->integer('references_box_id')->nullable()->after('origin_box_id');
        });

        Page::get()->each(function (Page $page) {
            $page->origin_page_id = $page->id;
            $page->save();

            $page->publishDraft();
        });

        Box::get()->each(function (Box $box) {
            $box->origin_box_id = $box->id;
            $box->save();
        });
    }

    public function down()
    {
        Schema::table('offline_boxes_pages', function (Blueprint $table) {
            $table->dropColumn('published_state');
            $table->dropColumn('published_at');
            $table->dropColumn('published_by');
            $table->dropColumn('updated_by');
        });
    }
}
