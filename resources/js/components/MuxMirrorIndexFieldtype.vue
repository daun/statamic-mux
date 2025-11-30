<template>
    <div class="flex flex-wrap items-center gap-2" v-if="isAsset && isVideo">
        <ui-icon v-if="!isUploaded" name="x-square" class="text-gray-400 dark:text-gray-600" v-tooltip="t('not_uploaded')" />
        <ui-icon v-if="isUploaded" name="checkmark" class="text-green-600" v-tooltip="t('uploaded')" />
        <ui-icon v-if="isUploaded && isProxy" name="page-ghost" class="size-3.5!" v-tooltip="t('proxy')" />
    </div>
</template>

<script>
import { IndexFieldtypeMixin as IndexFieldtype } from '@statamic/cms';

export default {
    mixins: [IndexFieldtype],
    computed: {
        extension() {
            return this.values.extension.toLowerCase();
        },
        isAsset() {
            return this.values.basename && this.values.extension;
        },
        isVideo() {
            return ['h264', 'mp4', 'm4v', 'ogv', 'webm', 'mov'].includes(this.extension);
        },
        isUploaded() {
            return this.value?.id || false;
        },
        isProxy() {
            return this.value?.is_proxy || false;
        },
    },
    methods: {
        t(key, replacements = {}) {
            return __(`statamic-mux::messages.fieldtype.${key}`, replacements);
        }
    },
};
</script>
