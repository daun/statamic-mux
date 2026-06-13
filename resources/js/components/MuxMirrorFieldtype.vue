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
                <ui-badge pill as="button" :icon="isInfoExpanded ? 'x-square' : 'info-square'" class="shadow-none!" v-tooltip="t('details_tooltip')" @click="toggleInfo">
                    {{ t('info') }}
                </ui-badge>
            </div>
            <ul v-if="isInfoExpanded" class="max-w-full grid p-1.5 border border-gray-150 dark:border-gray-700 rounded-lg overflow-hidden">
                <li
                    v-for="{ key, icon, label, value } in infoItems"
                    :key="key"
                    class="group flex min-w-0 max-w-full rounded-lg px-1 py-1.5 text-sm text-gray-700 dark:text-gray-300 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-800 outline-hidden"
                    @click="copy(value, key)"
                >
                    <div class="flex-shrink-0 flex size-5 items-center justify-center p-1">
                        <ui-icon v-if="icon" :name="icon" class="size-3.5! text-gray-400 dark:text-gray-500" />
                    </div>
                    <div class="flex-1 flex items-baseline gap-3 px-2 overflow-hidden">
                        <span class="shrink-0 font-medium">{{ label }}</span>
                        <span class="flex-1 truncate font-mono text-xs text-gray-400 dark:text-gray-500">
                            <span class="text-[0.9em]">{{ value }}</span>
                        </span>
                    </div>
                    <div class="flex-shrink-0 size-5 items-center justify-center p-1 hidden group-focus-within:flex! group-hover:flex!" :class="{ 'flex!': itemCopied === key }">
                        <ui-icon :name="itemCopied === key ? 'clipboard-check' : 'clipboard'" class="size-3.5! text-gray-400 dark:text-gray-500" :class="{ 'text-gray-800! dark:text-gray-200!': itemCopied === key }" />
                    </div>
                </li>
            </ul>
            <ui-checkbox v-if="showReuploadToggle" v-model="value.reupload" name="reupload" :label="t('reupload_on_save')" />
        </template>
    </div>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';

export default {
    mixins: [Fieldtype],
    data() {
        return {
            isInfoExpanded: false,
            itemCopied: null,
            itemCopiedTimeout: null,
        }
    },
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
        toggleInfo() {
            this.isInfoExpanded = !this.isInfoExpanded;
        },
        copy(value, key) {
            if (! value) {
                return;
            }

            Statamic.$callbacks.call('copyToClipboard', value);

            clearTimeout(this.itemCopiedTimeout);
            this.itemCopied = key;
            this.itemCopiedTimeout = setTimeout(() => {
                this.itemCopied = null;
            }, 4000);
        },
        t(key, replacements = {}) {
            return __(`statamic-mux::messages.fieldtype.${key}`, replacements);
        },
    },
};
</script>
