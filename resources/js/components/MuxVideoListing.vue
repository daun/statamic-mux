<template>
    <div>
        <Header icon="fieldtype-video" :title="title">
            <div class="flex items-center gap-2">

                <div v-if="can('manage mux')" class="flex items-center gap-2 sm:gap-3">
                    <!-- Actions (...) menu -->
                    <Dropdown align="start">
                        <template #trigger>
                            <Button icon="dots" variant="ghost" size="xs" :aria-label="__('Open dropdown menu')" @mousedown.prevent />
                        </template>
                        <DropdownMenu>
                            <DropdownItem icon="sync" :text="__('Clear cache and reload')" @click="refresh" />
                        </DropdownMenu>
                    </Dropdown>

                    <!-- Sync button -->
                    <ButtonGroup>
                        <Button
                            icon="sync"
                            :text="__('Mirror')"
                            :loading="runningCommand === 'mirror'"
                            :disabled="runningCommand !== null"
                            @click="runCommand('mirror')"
                        />
                        <Dropdown align="end">
                            <template #trigger>
                                <Button
                                    icon="chevron-down"
                                    :disabled="runningCommand !== null"
                                    class="ml-px"
                                />
                            </template>
                            <DropdownMenu>
                                <DropdownLabel :text="__('Run sync command')" />
                                <DropdownItem icon="sync" @click="runCommand('mirror')">
                                    <span class="flex items-baseline gap-2">
                                        <span>{{ __('Mirror') }}</span>
                                        <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Sync Statamic and Mux') }}</span>
                                    </span>
                                </DropdownItem>
                                <DropdownItem icon="upload-cloud" @click="runCommand('upload')">
                                    <span class="flex items-baseline gap-2">
                                        <span>{{ __('Upload') }}</span>
                                        <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Upload Statamic videos') }}</span>
                                    </span>
                                </DropdownItem>
                                <DropdownItem icon="ai-spark" @click="runCommand('prune')">
                                    <span class="flex items-baseline gap-2">
                                        <span>{{ __('Prune') }}</span>
                                        <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Delete orphaned Mux videos') }}</span>
                                    </span>
                                </DropdownItem>
                            </DropdownMenu>
                        </Dropdown>
                    </ButtonGroup>
                    <Button
                        v-if="dashboardUrl"
                        :href="dashboardUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        icon-append="external-link"
                        :text="__('Mux dashboard')"
                    />
                </div>
            </div>
        </Header>

        <Listing
            v-if="listingPage === 'mirrored'"
            ref="mirroredListing"
            :url="localEndpoint"
            :columns="localColumns"
            sort-column="title"
            sort-direction="asc"
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
                        v-if="row.can_edit && row.edit_url"
                        :href="row.edit_url"
                        target="_blank"
                        class="group inline-flex items-center gap-1 text-sm font-medium"
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

            <template #cell-_actions="{ row }">
                <div class="flex justify-end">
                    <Dropdown v-if="hasMuxActions(row)" align="end">
                        <DropdownMenu>
                            <DropdownItem icon="taxonomies" :text="__('Copy asset ID')" @click="copyAssetId(row)" />
                            <DropdownItem v-if="primaryPlaybackId(row)" icon="taxonomies" :text="__('Copy playback ID')" @click="copyPlaybackId(row)" />
                            <DropdownItem v-if="primaryPlaybackId(row)" icon="web" :text="__('Copy playback URL')" @click="copyPlaybackUrl(row)" />
                            <DropdownItem v-if="primaryPlaybackId(row)" icon="programming-code-block" :text="__('Copy embed code')" @click="copyEmbedCode(row)" />
                            <DropdownSeparator v-if="row.dashboard_url" />
                            <DropdownItem v-if="row.dashboard_url" icon="external-link-original" :text="__('Open in Mux dashboard')" :href="row.dashboard_url" target="_blank" rel="noopener noreferrer" />
                        </DropdownMenu>
                    </Dropdown>
                </div>
            </template>
        </Listing>

        <Listing
            v-else
            ref="libraryListing"
            :url="remoteEndpoint"
            :columns="remoteColumns"
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
                        class="group inline-flex items-center gap-1 text-sm font-medium"
                    >
                        <span>{{ value }}</span>
                        <Icon
                            name="external-link"
                            class="size-2 text-gray-400 opacity-0 transition-opacity group-hover:opacity-100 group-focus:opacity-100 group-focus-visible:opacity-100"
                            aria-hidden="true"
                        />
                    </a>
                    <span v-else class="text-sm font-medium">{{ value }}</span>
                    <span class="block text-2xs text-gray-500 dark:text-dark-175 font-mono">{{ row.mux_id }}</span>
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

            <template #cell-_actions="{ row }">
                <div class="flex justify-end">
                    <Dropdown v-if="hasMuxActions(row)" align="end">
                        <DropdownMenu>
                            <DropdownItem icon="taxonomies" :text="__('Copy asset ID')" @click="copyAssetId(row)" />
                            <DropdownItem v-if="primaryPlaybackId(row)" icon="taxonomies" :text="__('Copy playback ID')" @click="copyPlaybackId(row)" />
                            <DropdownItem v-if="primaryPlaybackId(row)" icon="web" :text="__('Copy playback URL')" @click="copyPlaybackUrl(row)" />
                            <DropdownItem v-if="primaryPlaybackId(row)" icon="programming-code-block" :text="__('Copy embed code')" @click="copyEmbedCode(row)" />
                            <DropdownSeparator v-if="row.dashboard_url" />
                            <DropdownItem v-if="row.dashboard_url" icon="external-link-original" :text="__('Open in Mux dashboard')" :href="row.dashboard_url" target="_blank" rel="noopener noreferrer" />
                        </DropdownMenu>
                    </Dropdown>
                </div>
            </template>
        </Listing>
    </div>
</template>

<script>
import {
    Badge,
    Button,
    ButtonGroup,
    Dropdown,
    DropdownItem,
    DropdownLabel,
    DropdownMenu,
    DropdownSeparator,
    Header,
    Icon,
    Listing,
} from '@statamic/cms/ui';

export default {
    components: { Badge, Button, ButtonGroup, Dropdown, DropdownItem, DropdownLabel, DropdownMenu, DropdownSeparator, Header, Icon, Listing },

    props: {
        title: { type: String, required: true },
        listingPage: { type: String, required: true },
        localEndpoint: { type: String, required: true },
        remoteEndpoint: { type: String, required: true },
        refreshEndpoint: { type: String, required: true },
        commandEndpoint: { type: String, required: true },
        dashboardUrl: { type: String, default: null },
    },

    data() {
        return {
            refreshing: false,
            runningCommand: null,
            localColumns: [
                { field: 'thumbnail_url', label: __('Thumbnail'), sortable: false },
                { field: 'title', label: __('Title'), sortable: true },
                { field: 'status', label: __('Status'), sortable: true },
                { field: 'is_stale', label: __('State'), sortable: true },
                { field: 'duration', label: __('Duration'), sortable: true },
                { field: 'playback_policy', label: __('Policy'), sortable: true },
                { field: 'created_at', label: __('Mux Created'), sortable: true },
                { field: '_actions', label: '', sortable: false, width: '1%' },
            ],
            remoteColumns: [
                { field: 'thumbnail_url', label: __('Thumbnail'), sortable: false },
                { field: 'title', label: __('Title'), sortable: true },
                { field: 'state', label: __('State'), sortable: true },
                { field: 'status', label: __('Status'), sortable: true },
                { field: 'duration', label: __('Duration'), sortable: true },
                { field: 'playback_policy', label: __('Policy'), sortable: true },
                { field: 'created_at', label: __('Created'), sortable: true },
                { field: '_actions', label: '', sortable: false, width: '1%' },
            ],
        };
    },

    methods: {
        async runCommand(command) {
            if (this.runningCommand) return;

            if (command === 'prune' && !window.confirm(__('Prune orphaned videos from Mux? This queues deletion jobs for remote videos with no local asset.'))) {
                return;
            }

            if (!this.commandEndpoint) return;

            this.runningCommand = command;
            try {
                const response = await this.$axios.post(this.commandEndpoint, { command });
                Statamic.$toast.success(response.data.message || __('Mux command queued. Refresh later to see updates.'));
            } catch (e) {
                console.error(e);
                Statamic.$toast.error(e.response?.data?.message || __('Failed to queue Mux command'));
            } finally {
                this.runningCommand = null;
            }
        },

        async refresh() {
            this.refreshing = true;
            try {
                await this.$axios.post(this.refreshEndpoint);
                this.$refs.mirroredListing?.refresh();
                this.$refs.libraryListing?.refresh();
                Statamic.$toast.success(__('Mux Library refreshed'));
            } catch (e) {
                console.error(e);
                console.error(e);
                Statamic.$toast.error(__('Failed to refresh Mux Library'));
            } finally {
                this.refreshing = false;
            }
        },

        hasMuxActions(row) {
            return Boolean(row?.mux_id);
        },

        primaryPlaybackId(row) {
            if (row?.playback_id) return row.playback_id;

            const playbackIds = Array.isArray(row?.playback_ids) ? row.playback_ids : [];
            return playbackIds.find((id) => id.policy === 'public')?.id || playbackIds[0]?.id || null;
        },

        playerUrl(row) {
            const playbackId = this.primaryPlaybackId(row);

            return playbackId
                ? `https://player.mux.com/${playbackId}`
                : null;
        },

        embedCode(row) {
            const url = this.playerUrl(row);

            return url
                ? `<iframe src="${url}" style="width: 100%; border: none; aspect-ratio: 16/9;" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;" allowfullscreen ></iframe>`
                : null;
        },

        async copyToClipboard(value) {
            if (value) {
                Statamic.$callbacks.call('copyToClipboard', value);
            }
        },

        copyAssetId(row) {
            return this.copyToClipboard(row.mux_id);
        },

        copyPlaybackId(row) {
            return this.copyToClipboard(this.primaryPlaybackId(row));
        },

        copyPlaybackUrl(row) {
            return this.copyToClipboard(this.playerUrl(row));
        },

        copyEmbedCode(row) {
            return this.copyToClipboard(this.embedCode(row));
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
