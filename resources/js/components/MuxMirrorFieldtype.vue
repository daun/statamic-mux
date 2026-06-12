<template>
    <div class="grid grid-cols-[minmax(0,1fr)] gap-3 justify-items-start">
        <template v-if="!isAsset || !isVideo">
            <ui-badge pill icon="focus" color="white" v-tooltip="t(!isVideo ? 'unmirrored_no_video' : 'unmirrored_no_asset')">
                {{ t('unmirrored') }}
            </ui-badge>
        </template>
        <template v-else-if="!isUploaded">
            <ui-badge pill icon="x-square" color="amber">
                {{ t('not_uploaded') }}
            </ui-badge>
            <ui-checkbox v-if="showReuploadToggle" v-model="value.reupload" name="reupload" :label="t('upload_on_save')" />
        </template>
        <template v-else>
            <div class="flex flex-wrap gap-2">
                <ui-badge pill icon="checkmark" color="green" v-tooltip="t('uploaded_tooltip')">
                    {{ t('uploaded') }}
                </ui-badge>
                <ui-badge pill v-if="isProxy" icon="page-ghost" v-tooltip="t('proxy_tooltip')">
                    {{ t('proxy') }}
                </ui-badge>
                <ui-dropdown v-if="showDetails && infoItems.length" align="start">
                    <template #trigger>
                        <ui-badge pill as="button" icon="info-square" v-tooltip="t('details_tooltip')">
                            {{ t('info') }}
                        </ui-badge>
                    </template>
                    <ui-dropdown-menu class="w-120 max-w-full">
                        <ui-dropdown-item
                            v-for="item in infoItems"
                            :key="item.key"
                            :icon="item.icon"
                            @click="copy(item)"
                        >
                            <span class="flex min-w-0 max-w-full items-baseline gap-3">
                                <span class="shrink-0 font-medium">{{ item.label }}</span>
                                <span class="min-w-0 flex-1 truncate font-mono text-xs text-gray-400 dark:text-gray-500">
                                    <span class="text-[0.9em]">{{ item.value }}</span>
                                </span>
                            </span>
                        </ui-dropdown-item>
                    </ui-dropdown-menu>
                </ui-dropdown>
            </div>

            <ui-checkbox v-if="showReuploadToggle" v-model="value.reupload" name="reupload" :label="t('reupload_on_save')" />
        </template>
    </div>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';

export default {
    mixins: [Fieldtype],
    computed: {
        showReuploadToggle() {
            return this.allowReuploads && !this.isProxy;
        },
        allowReuploads() {
            return this.config?.allow_reupload;
        },
        showDetails() {
            return this.config?.show_details;
        },
        isAsset() {
            return this.meta?.is_asset || false;
        },
        isVideo() {
            return this.meta?.is_video || false;
        },
        isUploaded() {
            return !!this.value.id;
        },
        isProxy() {
            return this.meta?.is_proxy || false;
        },
        infoItems() {
            const info = this.meta?.mux || {};

            const items = [
                { key: 'asset_id', icon: 'taxonomies' },
                { key: 'playback_id', icon: 'taxonomies' },
                { key: 'player_url', icon: 'mux::video-square' },
                // { key: 'stream_url', icon: 'mux::streaming' },
                { key: 'embed_code', icon: 'programming-code-block' },
                // { key: 'thumbnail_url', icon: 'mux::thumbnail' },
                { key: 'gif_url', icon: 'mux::thumbnail' },
            ];

            return items
                .filter((item) => info[item.key])
                .map((item) => ({ ...item, label: this.t(`copy.${item.key}`), value: info[item.key] }));
        },
    },
    methods: {
        copy(item) {
            if (item?.value) {
                Statamic.$callbacks.call('copyToClipboard', item.value);
            }
        },
        t(key, replacements = {}) {
            return __(`statamic-mux::messages.fieldtype.${key}`, replacements);
        },
    },
};
</script>
