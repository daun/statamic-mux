<template>
    <div>
        <Header icon="mux::video-playlist" :title="__('Mirrored Assets')">
            <div v-if="can('manage mux')" class="flex items-center gap-2 sm:gap-3">
                <ButtonGroup>
                    <Dropdown align="end">
                        <template #trigger>
                            <Button
                                icon="mux::reload"
                                :text="__('Sync')"
                                :loading="runningCommand"
                                :disabled="runningCommand !== null"
                            />
                        </template>
                        <DropdownMenu>
                            <DropdownItem icon="mux::cloud-transfer" @click="runCommand('mirror')">
                                <span class="flex items-baseline gap-2">
                                    <span class="font-medium">{{ __('Mirror') }}</span>
                                    <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Upload and prune') }}</span>
                                </span>
                            </DropdownItem>
                            <DropdownSeparator />
                            <DropdownItem icon="mux::cloud-upload" @click="runCommand('upload')">
                                <span class="flex items-baseline gap-2">
                                    <span class="font-medium">{{ __('Upload') }}</span>
                                    <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Upload new videos to Mux') }}</span>
                                </span>
                            </DropdownItem>
                            <DropdownItem icon="mux::cloud-spark" @click="runCommand('prune')">
                                <span class="flex items-baseline gap-2">
                                    <span class="font-medium">{{ __('Prune') }}</span>
                                    <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Delete orphaned Mux videos') }}</span>
                                </span>
                            </DropdownItem>
                        </DropdownMenu>
                    </Dropdown>
                </ButtonGroup>
            </div>
        </Header>

        <Listing
            ref="listing"
            :url="endpoint"
            :columns="columns"
            sort-column="title"
            sort-direction="asc"
            :allow-bulk-actions="false"
            :allow-presets="false"
            :allow-customizing-columns="false"
        >
            <template #cell-thumbnail_url="{ row, value }">
                <button
                    v-if="row.can_edit && row.edit_url"
                    type="button"
                    class="w-16 h-10 rounded overflow-hidden bg-gray-200 dark:bg-dark-700 flex items-center justify-center cursor-pointer"
                    :title="__('Edit Asset')"
                    :aria-label="__('Edit Asset')"
                    @click="openAssetEditor(row)"
                >
                    <img v-if="value" :src="value" class="w-full h-full object-cover" loading="lazy" @error="$event.target.style.display='none'" />
                    <Icon v-else name="movie-video-clip" class="size-5 text-gray-400" />
                </button>
                <div v-else class="w-16 h-10 rounded overflow-hidden bg-gray-200 dark:bg-dark-700 flex items-center justify-center">
                    <img v-if="value" :src="value" class="w-full h-full object-cover" loading="lazy" @error="$event.target.style.display='none'" />
                    <Icon v-else name="movie-video-clip" class="size-5 text-gray-400" />
                </div>
            </template>

            <template #cell-title="{ row, value }">
                <div>
                    <a
                        v-if="row.can_edit && row.edit_url"
                        :href="row.edit_url"
                        class="group inline-flex items-center gap-1 text-sm font-medium"
                        @click.prevent="openAssetEditor(row)"
                    >{{ value }}</a>
                    <span v-else class="text-sm font-medium">{{ value }}</span>
                    <span v-if="row.path" class="block text-2xs text-gray-500 dark:text-dark-175">{{ row.path }}</span>
                </div>
            </template>

            <template #cell-is_stale="{ row }">
                <Badge v-if="row.is_stale" pill color="red" class="text-2xs">{{ __('Stale') }}</Badge>
                <Badge v-else-if="row.has_mux_data && row.exists_remotely" pill color="green" class="text-2xs">{{ __('Mirrored') }}</Badge>
                <Badge v-else-if="!row.has_mux_data" pill color="gray" class="text-2xs">{{ __('Waiting') }}</Badge>
            </template>

            <template #cell-status="{ value }">
                <Badge pill :color="statusColor(value)" class="text-2xs">{{ statusLabel(value) }}</Badge>
            </template>

            <template #cell-duration="{ value }">
                <span v-if="value" class="text-sm tabular-nums" v-text="formatDuration(value)" />
                <span v-else class="text-gray-400">—</span>
            </template>

            <template #cell-playback_policy="{ value }">
                <Badge v-if="value" pill class="text-2xs capitalize">{{ value }}</Badge>
                <span v-else class="text-gray-400">—</span>
            </template>

            <template #cell-created_at="{ value }">
                <span v-if="value" class="text-sm tabular-nums" v-text="formatDate(value)" />
                <span v-else class="text-gray-400">—</span>
            </template>

            <template #cell-_actions="{ row }">
                <div class="flex justify-end">
                    <Dropdown v-if="hasActions(row)" align="end">
                        <DropdownMenu>
                            <DropdownItem v-if="canEditAsset(row)" icon="edit" :text="__('Edit asset')" @click="openAssetEditor(row)" />
                            <DropdownSeparator v-if="row.dashboard_url || playerUrl(row)" />
                            <DropdownItem v-if="row.dashboard_url" icon="external-link-original" :text="__('Open in Mux dashboard')" :href="row.dashboard_url" target="_blank" />
                            <DropdownItem v-if="playerUrl(row)" icon="external-link-original" :text="__('Open playback page')" :href="playerUrl(row)" target="_blank" />
                            <DropdownSeparator v-if="canEditAsset(row) && hasMuxActions(row)" />
                            <DropdownItem v-if="hasMuxActions(row)" icon="taxonomies" :text="__('Copy asset ID')" @click="copyAssetId(row)" />
                            <DropdownItem v-if="primaryPlaybackId(row)" icon="taxonomies" :text="__('Copy playback ID')" @click="copyPlaybackId(row)" />
                            <DropdownItem v-if="primaryPlaybackId(row)" icon="web" :text="__('Copy playback URL')" @click="copyPlaybackUrl(row)" />
                            <DropdownItem v-if="primaryPlaybackId(row)" icon="programming-code-block" :text="__('Copy embed code')" @click="copyEmbedCode(row)" />
                        </DropdownMenu>
                    </Dropdown>
                </div>
            </template>
        </Listing>

        <component
            :is="assetEditor"
            v-if="assetEditor && editingAssetId"
            :id="editingAssetId"
            :showToolbar="false"
            :allow-deleting="false"
            @closed="closeAssetEditor"
            @saved="assetEditorSaved"
            @action-completed="assetEditorActionCompleted"
        />
    </div>
</template>

<script>
import { markRaw } from 'vue';
import MuxPageMixin from './MuxPageMixin';

// Statamic 6 no longer registers the asset editor globally nor exports it from
// `@statamic/cms`. The component is only bundled into an internal, content-hashed
// CP chunk. The controller resolves that chunk's public URL(s) from Statamic's
// Vite manifest and passes them as `assetEditorChunks`; we dynamic-import them at
// runtime. The browser keys ES modules by URL, so this returns the same module
// instance Statamic already loaded (same Vue + deps), letting us reuse the editor.
// We identify it by its distinctive emits signature rather than a minified export
// name, so resolution survives Statamic rebuilds.
function isAssetEditor(component) {
    const emits = component?.emits;
    if (!Array.isArray(emits)) return false;

    return ['previous', 'next', 'saved', 'closed', 'action-completed'].every((event) =>
        emits.includes(event),
    );
}

export default {
    mixins: [MuxPageMixin],

    props: {
        endpoint: { type: String, default: null },
        commandEndpoint: { type: String, default: null },
        dashboardUrl: { type: String, default: null },
        assetEditorChunks: { type: Array, default: () => [] },
    },

    data() {
        return {
            assetEditor: null,
            // Set to true once the inline editor is resolved on mount. Stays false
            // if it can't be loaded (e.g. no CP build published, or running in dev
            // mode), in which case we fall back to opening the asset's edit page in
            // a new tab.
            assetEditorAvailable: false,
            editingAssetId: null,
            columns: [
                { field: 'thumbnail_url', label: __('Thumbnail'), sortable: false },
                { field: 'title', label: __('Title'), sortable: true },
                { field: 'status', label: __('Processing'), sortable: true },
                { field: 'is_stale', label: __('State'), sortable: true },
                { field: 'duration', label: __('Duration'), sortable: true },
                { field: 'playback_policy', label: __('Policy'), sortable: true },
                { field: 'created_at', label: __('Mux Created'), sortable: true },
                { field: '_actions', label: '', sortable: false, width: '1%' },
            ],
        };
    },

    mounted() {
        this.loadAssetEditor();
    },

    methods: {
        async loadAssetEditor() {
            for (const url of this.assetEditorChunks) {
                try {
                    const module = await import(/* @vite-ignore */ url);
                    const component = Object.values(module).find(isAssetEditor);

                    if (component) {
                        this.assetEditor = markRaw(component);
                        this.assetEditorAvailable = true;
                        return;
                    }
                } catch (error) {
                    // Try the next candidate chunk.
                }
            }

            console.warn(
                '[mux] Could not resolve the Statamic asset editor component; ' +
                    'falling back to opening assets in a new tab.',
            );
        },

        canEditAsset(row) {
            return Boolean(row?.can_edit && row?.id);
        },

        hasActions(row) {
            return this.canEditAsset(row) || this.hasMuxActions(row);
        },

        openAssetEditor(row) {
            if (!this.canEditAsset(row)) return;

            // If the inline editor couldn't be loaded, open the asset's edit
            // page in a new tab instead.
            if (!this.assetEditorAvailable) {
                if (row.edit_url) {
                    window.open(row.edit_url, '_blank', 'noopener');
                }
                return;
            }

            this.editingAssetId = row.id;
        },

        closeAssetEditor() {
            this.editingAssetId = null;
        },

        assetEditorSaved() {
            this.$refs.listing?.refresh();

            // Close on save, like Statamic's native asset editor. The editor emits
            // `saved` *before* it schedules `$nextTick(() => $refs.container
            // .clearDirtyState())`, so closing now – or via `$nextTick` (queued
            // FIFO, ours would run first) – unmounts it before that cleanup runs
            // and throws on a null `$refs.container`. A macrotask runs after all
            // microtasks (including the editor's nextTick), so the editor stays
            // mounted for its cleanup, then we close. Statamic's own `saveAndClose`
            // achieves the same ordering by chaining the close after `save()`.
            setTimeout(() => this.closeAssetEditor(), 0);
        },

        assetEditorActionCompleted() {
            this.$refs.listing?.refresh();
        },
    },
};
</script>
