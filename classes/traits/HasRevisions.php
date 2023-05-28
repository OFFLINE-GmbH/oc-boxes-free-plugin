<?php

namespace OFFLINE\Boxes\Classes\Traits;

use Backend\Facades\BackendAuth;
use Backend\Models\User;
use October\Rain\Database\Builder;
use OFFLINE\Boxes\Classes\CMS\ThemeResolver;
use OFFLINE\Boxes\Classes\PublishedState;
use OFFLINE\Boxes\Models\BoxesSetting;
use OFFLINE\Boxes\Models\Page;
use Site;

/**
 * Handle publishing state.
 *
 * @method static Builder drafts()
 * @method static Builder currentDrafts()
 * @method static Builder current()
 * @method static Builder currentPublished()
 */
trait HasRevisions
{
    public bool $wasRecentlyPublished = false;

    public static function bootHasRevisions()
    {
        static::extend(function (Page $page) {
            $page->dates[] = 'published_at';

            $page->hasMany['revisions'] = [
                Page::class,
                'key' => 'origin_page_id',
                'replicate' => false,
            ];

            $page->belongsTo['published_by_user'] = [
                User::class,
                'key' => 'published_by',
                'replicate' => false,
            ];

            $page->bindEvent('model.beforeValidate', function () use ($page) {
                if (!$page->published_state) {
                    $page->published_state = PublishedState::DRAFT;
                }
            });

            $page->bindEvent('model.afterCreate', function () use ($page) {
                if (!$page->origin_page_id) {
                    $page->origin_page_id = $page->id;
                    $page->save(null, $page->sessionKey);
                }
            });

            $page->bindEvent('model.beforeSave', function () use ($page) {
                $page->setPendingChangesFlag();
            });
        });
    }

    /**
     * Deletes a page with all of its children and revisions.
     */
    public function deleteEverything(): void
    {
        $this->useNestedTreeStructure = false;

        if ($this->children) {
            $this->children->each->deleteEverything();
        }

        $this->deleteAllRevisions();

        $this->delete();
    }

    /**
     * Mark a draft as "has pending changes".
     * This allows us to display the "publish" button again since there are new changes.
     */
    public function setPendingChangesFlag(): void
    {
        if ($this->published_state === PublishedState::DRAFT && !$this->wasRecentlyPublished) {
            $this->has_pending_changes = true;
        }
    }

    /**
     * Return the number of all unpublished drafts.
     */
    public static function getUnpublishedDraftCount(): int
    {
        return Page::query()
            ->where('published_state', PublishedState::DRAFT)
            ->where('has_pending_changes', true)
            ->count();
    }

    /**
     * Publish a draft.
     */
    public function publishDraft(): Page
    {
        $current = self::getPublishedRevision($this->origin_page_id);

        if ($current) {
            $current->published_state = PublishedState::OUTDATED;
            $current->save();
        }

        $user = BackendAuth::getUser();

        $this->published_by = $user?->id;
        $this->has_pending_changes = false;
        $this->wasRecentlyPublished = true;
        $this->save();

        $published = $this->replicateWithRelations();
        $published->useNestedTreeStructure = false;
        $published->published_state = PublishedState::PUBLISHED;
        $published->origin_page_id = $this->origin_page_id;
        $published->published_at = now();
        $published->published_by = $user?->id;
        $published->has_pending_changes = false;
        $published->version = $this->getNextPageVersion($this->origin_page_id);

        // The newly published version has to become a child of the currently published parent.
        if ($this->parent_id) {
            $publishedParent = self::getPublishedRevision($this->parent_id);

            if ($publishedParent) {
                $published->parent_id = $publishedParent->id;
            }
        }

        $result = tap($published)->save();

        // Newly published nested boxes need to become a child of the newly published parent box.
        $this->moveNewNestedBoxesToNewParents($this, $published);

        if (BoxesSetting::get('revisions_cleanup_enabled', false)) {
            $this->cleanupRevisions();
        }

        return $result;
    }

    /**
     * Converts any page version to the current draft.
     */
    public function restoreAsDraft(): Page
    {
        $currentDraft = Page::query()
            ->where('origin_page_id', $this->origin_page_id)
            ->where('published_state', PublishedState::DRAFT)
            ->first();

        if ($currentDraft) {
            $currentDraft->published_state = PublishedState::OUTDATED;
            $currentDraft->useNestedTreeStructure = false;
            $currentDraft->has_pending_changes = false;
            $currentDraft->version = $this->getNextPageVersion($this->origin_page_id);
            $currentDraft->save();
        }

        $restored = $this->replicateWithRelations();
        $restored->published_state = PublishedState::DRAFT;
        $restored->useNestedTreeStructure = false;
        $restored->origin_page_id = $this->origin_page_id;
        $restored->has_pending_changes = true;
        $restored->published_at = null;
        $restored->published_by = null;
        $restored->version = null;

        return tap($restored)->save();
    }

    /**
     * Return the currently published revision.
     */
    public static function getPublishedRevision(int $pageId): ?Page
    {
        return Page::query()
            ->where('origin_page_id', $pageId)
            ->where('published_state', PublishedState::PUBLISHED)
            ->orderBy('published_at', 'desc')
            ->first();
    }

    public function scopeDrafts($query)
    {
        $query->where('published_state', PublishedState::DRAFT)->orderBy('created_at', 'desc');
    }

    public function scopePublished($query)
    {
        $query->where('published_state', PublishedState::PUBLISHED)->orderBy('published_at', 'desc');
    }

    /**
     * Returns the latest draft (for admins) or the latest published version.
     * @param mixed $query
     */
    public function scopeCurrent($query)
    {
        $state = BackendAuth::getUser() ? PublishedState::DRAFT : PublishedState::PUBLISHED;

        return $this->scopeCurrentByPublishingState($query, $state);
    }

    public function scopeCurrentPublished($query)
    {
        return $this->scopeCurrentByPublishingState($query, PublishedState::PUBLISHED);
    }

    public function scopeCurrentDrafts($query)
    {
        return $this->scopeCurrentByPublishingState($query, PublishedState::DRAFT);
    }

    public function scopeCurrentByPublishingState($query, string $state)
    {
        return $query->whereIn('id', function ($query) use ($state) {
            $query
                ->from($this->table, 'a')
                ->where('a.theme', ThemeResolver::instance()?->getThemeCode())
                ->when(
                    $site = Site::getSiteIdFromContext(),
                    fn ($q) => $q->where('a.site_id', $site)
                )
                ->leftJoin($this->table . ' as b', function ($join) {
                    $join->on('a.origin_page_id', '=', 'b.origin_page_id')
                        ->on('a.published_at', '<', 'b.published_at')
                        ->on('a.published_state', '=', 'b.published_state');
                })
                ->whereNull('b.id')
                ->where('a.published_state', $state)
                ->orderBy('a.published_at', 'desc')
                ->selectRaw('a.id');
        });
    }

    /**
     * Returns the next page version.
     */
    public function getNextPageVersion(?int $originPageId = null): int
    {
        return self::query()
            ->where('origin_page_id', $originPageId ?? $this->origin_page_id)
            ->max('version') + 1;
    }

    /**
     * Delete all revisions of this page.
     */
    protected function deleteAllRevisions(): void
    {
        self::query()
            ->where('origin_page_id', $this->origin_page_id)
            ->get()
            ->each
            ->delete();
    }

    /**
     * Delete old revisions of this page.
     * By default,
     * - the last 20 revisions are kept.
     * - only data older than two weeks is deleted.
     */
    protected function cleanupRevisions(): void
    {
        self::query()
            ->where('origin_page_id', $this->origin_page_id)
            ->where('published_at', '<', now()->subDays(BoxesSetting::get('revisions_keep_days', 14)))
            ->where('published_state', PublishedState::OUTDATED)
            ->orderBy('published_at', 'desc')
            ->skip(BoxesSetting::get('revisions_keep_number', 20))
            ->limit(100) // Limit to prevent deleting too many revisions at once.
            ->get()
            ->each
            ->delete();
    }
}
