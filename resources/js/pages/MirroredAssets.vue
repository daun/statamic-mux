<template>
    <div>
        <Header icon="fieldtype-video" :title="__('Mirrored Assets')">
            <div v-if="can('manage mux')" class="flex items-center gap-2 sm:gap-3">
                <ButtonGroup>
                    <Dropdown align="end">
                        <template #trigger>
                            <Button
                                icon="sync"
                                :text="__('Sync')"
                                :loading="runningCommand"
                                :disabled="runningCommand !== null"
                            />
                        </template>
                        <DropdownMenu>
                            <DropdownLabel :text="__('Run sync command')" />
                            <DropdownItem icon="sync" @click="runCommand('mirror')">
                                <span class="flex items-baseline gap-2">
                                    <span class="font-medium">{{ __('Mirror') }}</span>
                                    <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Upload and prune') }}</span>
                                </span>
                            </DropdownItem>
                            <DropdownItem icon="upload-cloud" @click="runCommand('upload')">
                                <span class="flex items-baseline gap-2">
                                    <span>{{ __('Upload') }}</span>
                                    <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('Upload new videos to Mux') }}</span>
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
import MuxPageMixin from './MuxPageMixin';

export default {
    mixins: [MuxPageMixin],

    props: {
        endpoint: { type: String, default: null },
        commandEndpoint: { type: String, default: null },
        dashboardUrl: { type: String, default: null },
    },

    data() {
        return {
            columns: [
                { field: 'thumbnail_url', label: __('Thumbnail'), sortable: false },
                { field: 'title', label: __('Title'), sortable: true },
                { field: 'status', label: __('Status'), sortable: true },
                { field: 'is_stale', label: __('State'), sortable: true },
                { field: 'duration', label: __('Duration'), sortable: true },
                { field: 'playback_policy', label: __('Policy'), sortable: true },
                { field: 'created_at', label: __('Mux Created'), sortable: true },
                { field: '_actions', label: '', sortable: false, width: '1%' },
            ],
        };
    },

};
</script>
