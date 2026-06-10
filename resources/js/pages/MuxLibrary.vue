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
                <SyncButton :endpoint="commandEndpoint" />
                <Button
                    v-if="dashboardUrl"
                    :href="dashboardUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    icon-append="external-link"
                    :text="__('Mux Dashboard')"
                />
            </div>
        </Header>

        <Listing
            ref="listing"
            :url="endpoint"
            :columns="columns"
            :action-url="actionUrl"
            sort-column="created_at"
            sort-direction="desc"
            :allow-bulk-actions="true"
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
                            name="external-link"
                            class="size-3! text-gray-400 opacity-0 transition-opacity group-hover:opacity-100 group-focus:opacity-100 group-focus-visible:opacity-100"
                            aria-hidden="true"
                        />
                    </a>
                    <span v-else class="text-sm font-medium">{{ value }}</span>
                    <span class="block text-2xs text-gray-500 dark:text-dark-175 font-mono">{{ row.mux_id }}</span>
                </div>
            </template>

            <template #cell-match_status="{ value }">
                <Badge pill :color="matchStatusColor(value)" class="text-2xs capitalize">{{ value }}</Badge>
            </template>

            <template #cell-processing_status="{ value }">
                <Badge v-if="value" pill :color="processingStatusColor(value)" class="text-2xs">{{ processingStatusLabel(value) }}</Badge>
            </template>

            <template #cell-duration="{ row }">
                <span v-if="row.duration_formatted" class="text-sm tabular-nums" v-text="row.duration_formatted" />
            </template>

            <template #cell-playback_policy="{ value }">
                <Badge v-if="value" pill class="text-2xs capitalize">{{ value }}</Badge>
            </template>

            <template #cell-created_at="{ value }">
                <date-time v-if="value" :of="value" date-only />
            </template>

            <template #prepended-row-actions="{ row }">
                <DropdownItem v-if="row.dashboard_url" icon="external-link" :text="__('Open in Mux dashboard')" :href="row.dashboard_url" target="_blank" />
                <DropdownItem v-if="playerUrl(row)" icon="external-link" :text="__('Open playback page')" :href="playerUrl(row)" target="_blank" />
                <DropdownSeparator v-if="row.dashboard_url || playerUrl(row)" />
                <DropdownItem icon="taxonomies" :text="__('Copy asset ID')" @click="copyAssetId(row)" />
                <DropdownItem v-if="primaryPlaybackId(row)" icon="taxonomies" :text="__('Copy playback ID')" @click="copyPlaybackId(row)" />
                <DropdownItem v-if="primaryPlaybackId(row)" icon="web" :text="__('Copy playback URL')" @click="copyPlaybackUrl(row)" />
                <DropdownItem v-if="primaryPlaybackId(row)" icon="programming-code-block" :text="__('Copy embed code')" @click="copyEmbedCode(row)" />
            </template>
        </Listing>
    </div>
</template>

<script>
import MuxPageMixin from './MuxPageMixin';

export default {
    mixins: [MuxPageMixin],

    props: {
        endpoint: { type: String, default: null },
        refreshEndpoint: { type: String, default: null },
        dashboardUrl: { type: String, default: null },
    },

    data() {
        return {
            refreshing: false,
            columns: [
                { field: 'thumbnail_url', label: __('Thumbnail'), sortable: false },
                { field: 'title', label: __('Title'), sortable: true },
                { field: 'duration', label: __('Duration'), sortable: true },
                { field: 'match_status', label: __('Status'), sortable: true },
                { field: 'processing_status', label: __('Processing'), sortable: true },
                { field: 'playback_policy', label: __('Policy'), sortable: true },
                { field: 'created_at', label: __('Created'), sortable: true },
            ],
        };
    },

    methods: {
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
