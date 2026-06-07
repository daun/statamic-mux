<template>
    <div>
        <Header icon="fieldtype-video" :title="__('Mux Videos')">
            <Button
                :icon="refreshing ? 'loading' : 'sync'"
                :text="refreshing ? __('Refreshing…') : __('Refresh')"
                :loading="refreshing"
                @click="refresh"
            />
        </Header>

        <Tabs v-model="activeTab">
            <TabList>
                <TabTrigger name="local" :text="__('Local Assets')" />
                <TabTrigger name="remote" :text="__('Remote Assets')" />
            </TabList>

            <TabContent name="local">
                <Listing
                    ref="localListing"
                    :url="localEndpoint"
                    :columns="localColumns"
                    sort-column="title"
                    sort-direction="asc"
                    :allow-bulk-actions="false"
                    :allow-presets="false"
                >
                    <template #cell-thumbnail_url="{ row, value }">
                        <div class="w-16 h-10 rounded overflow-hidden bg-gray-200 dark:bg-dark-700 flex items-center justify-center">
                            <img v-if="value" :src="value" class="w-full h-full object-cover" loading="lazy" @error="$event.target.style.display='none'" />
                            <Icon v-else name="movie-video-clip" class="size-5 text-gray-400" />
                        </div>
                    </template>

                    <template #cell-title="{ row, value }">
                        <div>
                            <span class="text-sm font-medium" v-text="value" />
                            <span v-if="row.path" class="block text-2xs text-gray-500 dark:text-dark-175" v-text="row.path" />
                        </div>
                    </template>

                    <template #cell-is_stale="{ row }">
                        <Badge v-if="row.is_stale" pill color="red" class="text-2xs">{{ __('Stale') }}</Badge>
                        <Badge v-else-if="row.has_mux_data && row.exists_remotely" pill color="green" class="text-2xs">{{ __('Mirrored') }}</Badge>
                        <Badge v-else-if="!row.has_mux_data" pill color="gray" class="text-2xs">{{ __('Waiting') }}</Badge>
                    </template>

                    <template #cell-status="{ row, value }">
                        <Badge pill :color="statusColor(value)" class="text-2xs">{{ statusLabel(value) }}</Badge>
                    </template>

                    <template #cell-duration="{ row, value }">
                        <span v-if="value" class="text-sm tabular-nums" v-text="formatDuration(value)" />
                        <span v-else class="text-gray-400">—</span>
                    </template>

                    <template #cell-playback_policy="{ row, value }">
                        <Badge v-if="value" pill class="text-2xs capitalize">{{ value }}</Badge>
                        <span v-else class="text-gray-400">—</span>
                    </template>

                    <template #cell-created_at="{ row, value }">
                        <span v-if="value" class="text-sm tabular-nums" v-text="formatDate(value)" />
                        <span v-else class="text-gray-400">—</span>
                    </template>
                </Listing>
            </TabContent>

            <TabContent name="remote">
                <Listing
                    ref="remoteListing"
                    :url="remoteEndpoint"
                    :columns="remoteColumns"
                    sort-column="created_at"
                    sort-direction="desc"
                    :allow-bulk-actions="false"
                    :allow-presets="false"
                >
                    <template #cell-thumbnail_url="{ row, value }">
                        <div class="w-16 h-10 rounded overflow-hidden bg-gray-200 dark:bg-dark-700 flex items-center justify-center">
                            <img v-if="value" :src="value" class="w-full h-full object-cover" loading="lazy" @error="$event.target.style.display='none'" />
                            <Icon v-else name="movie-video-clip" class="size-5 text-gray-400" />
                        </div>
                    </template>

                    <template #cell-title="{ row, value }">
                        <div>
                            <span class="text-sm font-medium" v-text="value" />
                            <span class="block text-2xs text-gray-500 dark:text-dark-175 font-mono" v-text="row.mux_id" />
                        </div>
                    </template>

                    <template #cell-state="{ row, value }">
                        <Badge pill :color="stateColor(value)" class="text-2xs capitalize">{{ value }}</Badge>
                    </template>

                    <template #cell-status="{ row, value }">
                        <Badge pill :color="statusColor(value)" class="text-2xs">{{ statusLabel(value) }}</Badge>
                    </template>

                    <template #cell-duration="{ row, value }">
                        <span v-if="value" class="text-sm tabular-nums" v-text="formatDuration(value)" />
                        <span v-else class="text-gray-400">—</span>
                    </template>

                    <template #cell-playback_policy="{ row, value }">
                        <Badge v-if="value" pill class="text-2xs capitalize">{{ value }}</Badge>
                        <span v-else class="text-gray-400">—</span>
                    </template>

                    <template #cell-created_at="{ row, value }">
                        <span v-if="value" class="text-sm tabular-nums" v-text="formatDate(value)" />
                        <span v-else class="text-gray-400">—</span>
                    </template>
                </Listing>
            </TabContent>
        </Tabs>
    </div>
</template>

<script>
import {
    Badge,
    Button,
    Header,
    Icon,
    Listing,
    TabContent,
    TabList,
    Tabs,
    TabTrigger,
} from '@statamic/cms/ui';

export default {
    components: { Badge, Button, Header, Icon, Listing, TabContent, TabList, Tabs, TabTrigger },

    props: {
        localEndpoint: { type: String, required: true },
        remoteEndpoint: { type: String, required: true },
        refreshEndpoint: { type: String, required: true },
    },

    data() {
        return {
            activeTab: 'local',
            refreshing: false,
            localColumns: [
                { field: 'thumbnail_url', label: __('Thumbnail'), sortable: false, visible: true },
                { field: 'title', label: __('Title'), sortable: true, visible: true },
                { field: 'is_stale', label: __('State'), sortable: true, visible: true },
                { field: 'status', label: __('Status'), sortable: true, visible: true },
                { field: 'duration', label: __('Duration'), sortable: true, visible: true },
                { field: 'playback_policy', label: __('Policy'), sortable: true, visible: true },
                { field: 'created_at', label: __('Mux Created'), sortable: true, visible: true },
            ],
            remoteColumns: [
                { field: 'thumbnail_url', label: __('Thumbnail'), sortable: false, visible: true },
                { field: 'title', label: __('Title'), sortable: true, visible: true },
                { field: 'state', label: __('State'), sortable: true, visible: true },
                { field: 'status', label: __('Status'), sortable: true, visible: true },
                { field: 'duration', label: __('Duration'), sortable: true, visible: true },
                { field: 'playback_policy', label: __('Policy'), sortable: true, visible: true },
                { field: 'created_at', label: __('Created'), sortable: true, visible: true },
            ],
        };
    },

    methods: {
        async refresh() {
            this.refreshing = true;
            try {
                await Statamic.$axios.post(this.refreshEndpoint);
                this.$refs.localListing?.refresh();
                this.$refs.remoteListing?.refresh();
                Statamic.$toast.success(__('Remote assets refreshed'));
            } catch (e) {
                Statamic.$toast.error(__('Failed to refresh remote assets'));
            } finally {
                this.refreshing = false;
            }
        },

        statusColor(status) {
            return {
                ready: 'green',
                preparing: 'amber',
                waiting: 'gray',
                stale: 'red',
                errored: 'red',
            }[status] || 'gray';
        },

        statusLabel(status) {
            return {
                ready: __('Ready'),
                preparing: __('Preparing'),
                waiting: __('Waiting'),
                stale: __('Stale'),
                errored: __('Errored'),
            }[status] || status;
        },

        stateColor(state) {
            return {
                mirrored: 'green',
                orphaned: 'amber',
                duplicated: 'red',
            }[state] || 'gray';
        },

        formatDuration(seconds) {
            if (!seconds) return '—';
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = Math.floor(seconds % 60);
            if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
            return `${m}:${String(s).padStart(2, '0')}`;
        },

        formatDate(iso) {
            if (!iso) return '—';
            return new Date(iso).toLocaleDateString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        },
    },
};
</script>
