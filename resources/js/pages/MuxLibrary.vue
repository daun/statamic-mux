<template>
    <div>
        <Header icon="mux::cloud-video" :title="__('Mux Library')">
            <div v-if="can('manage mux')" class="flex items-center gap-2 sm:gap-3">
                <Dropdown align="end">
                    <template #trigger>
                        <Button
                            icon="dots"
                            variant="ghost"
                            size="sm"
                            :aria-label="__('Open dropdown menu')"
                        />
                    </template>
                    <DropdownMenu>
                        <DropdownItem icon="mux::reload" @click="refresh">
                            {{ __('Clear cache and reload') }}
                        </DropdownItem>
                    </DropdownMenu>
                </Dropdown>
                <Button
                    v-if="dashboardUrl"
                    :href="dashboardUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    icon-append="external-link-original"
                    :text="__('Mux Dashboard')"
                />
            </div>
        </Header>

        <Listing
            ref="listing"
            :url="endpoint"
            :columns="columns"
            sort-column="created_at"
            sort-direction="desc"
            :allow-bulk-actions="false"
            :allow-presets="false"
            :allow-customizing-columns="false"
        >
            <template #cell-thumbnail_url="{ row, value }">
                <div class="w-16 h-10 rounded overflow-hidden bg-gray-200 dark:bg-dark-700 flex items-center justify-center">
                    <img v-if="value" :src="value" class="w-full h-full object-cover" loading="lazy" @error="$event.target.style.display='none'" />
                    <Icon v-else name="movie-video-clip" class="size-5 text-gray-400" />
                </div>
            </template>

            <template #cell-title="{ row, value }">
                <div>
                    <a
                        v-if="row.dashboard_url"
                        :href="row.dashboard_url"
                        target="_blank"
                        class="group inline-flex items-center gap-2 text-sm font-medium"
                    >
                        <span>{{ value }}</span>
                        <Icon
                            name="external-link-original"
                            class="size-3! text-gray-400 opacity-0 transition-opacity group-hover:opacity-100 group-focus:opacity-100 group-focus-visible:opacity-100"
                            aria-hidden="true"
                        />
                    </a>
                    <span v-else class="text-sm font-medium">{{ value }}</span>
                    <span class="block text-2xs text-gray-500 dark:text-dark-175 font-mono">{{ row.mux_id }}</span>
                </div>
            </template>

            <template #cell-state="{ value }">
                <Badge pill :color="stateColor(value)" class="text-2xs capitalize">{{ value }}</Badge>
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
                    <Dropdown v-if="hasMuxActions(row)" align="end">
                        <DropdownMenu>
                            <DropdownItem v-if="row.dashboard_url" icon="external-link-original" :text="__('Open in Mux dashboard')" :href="row.dashboard_url" target="_blank" />
                            <DropdownItem v-if="playerUrl(row)" icon="external-link-original" :text="__('Open playback page')" :href="playerUrl(row)" target="_blank" />
                            <DropdownSeparator />
                            <DropdownItem icon="taxonomies" :text="__('Copy asset ID')" @click="copyAssetId(row)" />
                            <DropdownItem v-if="primaryPlaybackId(row)" icon="taxonomies" :text="__('Copy playback ID')" @click="copyPlaybackId(row)" />
                            <DropdownItem v-if="primaryPlaybackId(row)" icon="web" :text="__('Copy playback URL')" @click="copyPlaybackUrl(row)" />
                            <DropdownItem v-if="primaryPlaybackId(row)" icon="programming-code-block" :text="__('Copy embed code')" @click="copyEmbedCode(row)" />
                            <DropdownSeparator v-if="can('manage mux')" />
                            <DropdownItem v-if="can('manage mux')" icon="trash" variant="destructive" :text="__('Delete from Mux')" @click="confirmDelete(row)" />
                        </DropdownMenu>
                    </Dropdown>
                </div>
            </template>
        </Listing>
        <ConfirmationModal
            :open="showDeleteModal"
            :title="__('Delete Mux Asset')"
            :body-text="__('Are you sure you want to permanently delete this video from Mux? This action cannot be undone.')"
            :button-text="__('Delete')"
            :danger="true"
            :busy="deleting"
            @confirm="performDelete"
            @cancel="cancelDelete"
            @update:open="(open) => { if (!open) cancelDelete() }"
        />
    </div>
</template>

<script>
import MuxPageMixin from './MuxPageMixin';

export default {
    mixins: [MuxPageMixin],

    props: {
        endpoint: { type: String, default: null },
        refreshEndpoint: { type: String, default: null },
        commandEndpoint: { type: String, default: null },
        deleteEndpoint: { type: String, default: null },
        dashboardUrl: { type: String, default: null },
    },

    data() {
        return {
            refreshing: false,
            showDeleteModal: false,
            deletingRow: null,
            deleting: false,
            columns: [
                { field: 'thumbnail_url', label: __('Thumbnail'), sortable: false },
                { field: 'title', label: __('Title'), sortable: true },
                { field: 'state', label: __('State'), sortable: true },
                { field: 'status', label: __('Processing'), sortable: true },
                { field: 'duration', label: __('Duration'), sortable: true },
                { field: 'playback_policy', label: __('Policy'), sortable: true },
                { field: 'created_at', label: __('Created'), sortable: true },
                { field: '_actions', label: '', sortable: false, width: '1%' },
            ],
        };
    },

    methods: {
        confirmDelete(row) {
            this.deletingRow = row;
            this.showDeleteModal = true;
        },

        cancelDelete() {
            if (this.deleting) return;
            this.showDeleteModal = false;
            this.deletingRow = null;
        },

        async performDelete() {
            if (!this.deletingRow || !this.deleteEndpoint) return;

            this.deleting = true;
            try {
                const url = this.deleteEndpoint.replace('__MUX_ID__', this.deletingRow.mux_id);
                await this.$axios.delete(url);
                Statamic.$toast.success(__('Mux asset deleted'));
                this.showDeleteModal = false;
                this.deletingRow = null;
                this.$refs.listing?.refresh();
            } catch (e) {
                console.error(e);
                Statamic.$toast.error(e.response?.data?.message || __('Failed to delete Mux asset'));
            } finally {
                this.deleting = false;
            }
        },

        async refresh() {
            if (!this.refreshEndpoint) return;

            this.refreshing = true;
            try {
                await this.$axios.post(this.refreshEndpoint);
                this.$refs.listing?.refresh();
                Statamic.$toast.success(__('Mux Library refreshed'));
            } catch (e) {
                console.error(e);
                Statamic.$toast.error(__('Failed to refresh Mux Library'));
            } finally {
                this.refreshing = false;
            }
        },
    },
};
</script>
