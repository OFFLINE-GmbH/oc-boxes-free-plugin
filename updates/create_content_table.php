<?php

namespace OFFLINE\Boxes\Updates;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use OFFLINE\Boxes\Models\Box;
use OFFLINE\Boxes\Models\Content;
use OFFLINE\Boxes\Models\Page;
use Schema;

/**
 * CreateContentTable Migration
 */
class CreateContentTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('offline_boxes_content')) {
            Schema::create('offline_boxes_content', function (Blueprint $table) {
                $table->id();

                $table->string('slug')->nullable()->unique();
                $table->string('layout')->nullable();
                $table->string('theme')->nullable();
                $table->integer('content_id')->nullable();
                $table->string('content_type')->nullable();
                $table->boolean('is_pending_content')->default(false);
                $table->text('custom_config')->nullable();

                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('offline_boxes_boxes', 'holder_type')) {
            Schema::table('offline_boxes_boxes', function (Blueprint $table) {
                $table->string('holder_type')->nullable();
                $table->integer('holder_id')->nullable();
            });
        }

        $pageIdContentMap = [];

        Box::withoutGlobalScopes()->where('parent_id', null)->get()->each(function (Box $box) use (&$pageIdContentMap) {
            if ($box->page()->withoutGlobalScopes()->first()?->content_type) {
                $contentId = $pageIdContentMap[$box->page_id] ?? null;

                $box->holder_type = Content::class;

                if ($contentId) {
                    $box->holder_id = $contentId;
                } else {
                    $content = new Content();
                    $content->slug = $box->page->slug;
                    $content->layout = $box->page->layout;
                    $content->theme = $box->page->theme;
                    $content->custom_config = $box->page->custom_config;
                    $content->content_id = $box->page->content_id;
                    $content->content_type = $box->page->content_type;
                    $content->is_pending_content = false;
                    $content->save();

                    $pageIdContentMap[$box->page_id] = $content->id;

                    $box->holder_id = $content->id;

                    DB::table('system_files')
                        ->where('attachment_type', Page::class)
                        ->where('attachment_id', $box->page_id)
                        ->update([
                            'attachment_type' => Content::class,
                            'attachment_id' => $content->id,
                        ]);
                }
            } else {
                $box->holder_type = Page::class;
                $box->holder_id = $box->page_id;
            }

            $box->save();

            $box->children->each(function (Box $child) use ($box) {
                $child->holder_id = $box->holder_id;
                $child->holder_type = $box->holder_type;
                $child->save();
            });
        });

        $contentPagesRoot = Page::withoutGlobalScopes()->where('slug', 'system-content-pages')->first();
        $standaloneRoot = Page::withoutGlobalScopes()->where('slug', 'system-standalone-pages')->first();

        if ($standaloneRoot) {
            $newRoots = Page::withoutGlobalScopes()->orderBy('nest_left')->where(
                'parent_id',
                $standaloneRoot->id
            )->get();

            $newRoots->each(function ($page) {
                DB::table('offline_boxes_pages')
                    ->where('id', $page->id)
                    ->update(['parent_id' => null]);
            });

            DB::table('offline_boxes_pages')->where('id', $standaloneRoot->id)->delete();
        }

        if ($contentPagesRoot) {
            $contentPagesRoot->delete();
        }

        (new Page())->resetTreeNesting();

        Cache::clear();

        if (Schema::hasColumns('offline_boxes_pages', ['content_id', 'is_system_entry'])) {
            Schema::table('offline_boxes_pages', function (Blueprint $table) {
                $table->dropColumn('content_id');
                $table->dropColumn('content_type');
                $table->dropColumn('is_pending_content');
                $table->dropColumn('is_system_entry');
            });
        }

        Schema::table('offline_boxes_boxes', function (Blueprint $table) {
            $table->dropColumn('page_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('offline_boxes_content');
    }
}
