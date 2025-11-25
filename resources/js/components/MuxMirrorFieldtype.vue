<template>
    <div class="grid grid-cols-[minmax(0,1fr)] gap-3 justify-items-start">
        <template v-if="!isAsset || !isVideo">
            <ui-badge pill icon="unsynced" v-tooltip="t(!isVideo ? 'unmirrored_no_video' : 'unmirrored_no_asset')">
                {{ t('unmirrored') }}:
            </ui-badge>
        </template>
        <template v-else-if="!isUploaded">
            <ui-badge pill icon="unsynced" color="amber">
                {{ t('not_uploaded') }}
            </ui-badge>
            <div v-if="showReuploadToggle" class="flex items-center">
                <ui-checkbox v-model="value.reupload" name="reupload" :label="t('upload_on_save')" />
            </div>
        </template>
        <template v-else>
            <div class="flex flex-wrap gap-2">
                <ui-badge pill icon="checkmark" color="green" v-tooltip="t('uploaded_tooltip')">
                    {{ t('uploaded') }}
                </ui-badge>
                <ui-badge pill v-if="isProxy" icon="page-ghost" v-tooltip="t('proxy_tooltip')">
                    {{ t('proxy') }}
                </ui-badge>
                <ui-badge pill v-if="showDetails" icon="info-square" as="button" v-tooltip="t('details_tooltip')" @click="detailsExpanded = !detailsExpanded">
                    {{ t('details') }}
                </ui-badge>
            </div>
            <ui-card inset variant="flat" class="w-full overflow-auto" v-if="showDetails && detailsExpanded">
                <table class="w-full divide-y divide-gray-800/10 text-sm dark:divide-white/10">
                    <tbody class="[&_td]:p-2! [&_td:first-child]:pl-4! [&_td:last-child]:pr-4! [&_td]:text-left [&_svg]:opacity-60">
                        <tr key="id">
                            <td>
                                <ui-icon name="fingerprint" v-tooltip="'Mux ID'"></ui-icon>
                            </td>
                            <td>{{ value.id }}</td>
                        </tr>
                        <tr v-for="[policy, id] in playbackIds" :key="id">
                            <td v-if="policy === 'signed'">
                                <ui-icon name="key" v-tooltip="'Signed Playback ID'"></ui-icon>
                            </td>
                            <td v-else>
                                <ui-icon name="globals" v-tooltip="'Public Playback ID'"></ui-icon>
                            </td>
                            <td>{{ value.id }}</td>
                        </tr>
                    </tbody>
                </table>
            </ui-card>
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
            detailsExpanded: false
        };
    },
    computed: {
        showReuploadToggle() {
            return this.allowReuploads && ! this.isProxy;
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
        isProxy() {
            return this.meta?.is_proxy || false;
        },
        isUploaded() {
            return !! this.value.id;
        },
        playbackIds() {
            console.log(Object.entries(this.value.playback_ids || {}));
            return Object.entries(this.value.playback_ids || {});
        }
    },
    methods: {
        t(key, replacements = {}) {
            return __(`statamic-mux::messages.fieldtype.${key}`, replacements);
        }
    }
};
</script>
