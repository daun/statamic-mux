<template>
    <div class="flex flex-wrap gap-2" v-if="isAsset && isVideo">
        <template v-if="!isUploaded">
            <ui-badge pill color="amber">
                {{ t('not_uploaded') }}
            </ui-badge>
        </template>
        <template v-else>
            <ui-badge pill color="green">
                {{ t('uploaded') }}
            </ui-badge>
            <ui-badge v-if="isProxy" pill icon="page-ghost" v-tooltip="t('proxy')">
                <span class="sr-only">{{ t('proxy') }}</span>
            </ui-badge>
        </template>
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
