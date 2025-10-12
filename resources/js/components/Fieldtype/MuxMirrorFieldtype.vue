<template>
    <div>
        <div v-if="!isAsset || !isVideo">
            <DescriptionWithIcon icon="eye-slash">
                {{ __('statamic-mux::messages.mirror_fieldtype.not_mirrored') }}:
                <template v-if="!isVideo">
                    {{ __('statamic-mux::messages.mirror_fieldtype.no_video') }}
                </template>
                <template v-else>
                    {{ __('statamic-mux::messages.mirror_fieldtype.no_asset') }}
                </template>
            </DescriptionWithIcon>
        </div>
        <div v-else-if="!isUploaded">
            <DescriptionWithIcon icon="unsynced">
                {{ __('statamic-mux::messages.mirror_fieldtype.not_uploaded') }}
            </DescriptionWithIcon>
            <div v-if="allowReuploads" class="flex items-center mt-3">
                <label for="upload-missing-asset" class="help-block grow flex items-center cursor-pointer font-normal">
                    <span class="basis-6 flex items-center">
                        <input type="checkbox" name="reupload" id="upload-missing-asset" class="mr-2" v-model="value.reupload">
                    </span>
                    <span>{{ __('statamic-mux::messages.mirror_fieldtype.upload_on_save') }}</span>
                </label>
            </div>
        </div>
        <div v-else>
            <DescriptionWithIcon icon="synced">
                <span :title="this.value.id">
                    {{ __('statamic-mux::messages.mirror_fieldtype.uploaded') }}
                </span>
            </DescriptionWithIcon>
            <div v-if="details.length" class="mux-table-wrapper mt-3">
                <table class="mux-table">
                    <tbody>
                        <tr v-for="{ key, label, value, icon } in details" :key="key">
                            <th>{{ label || key }}</th>
                            <td>
                                <div class="flex align-center">
                                    <svg-icon v-if="icon" :name="'light/' + icon" class="h-4 w-4 mr-2 shrink-0" />
                                    <span>{{ value }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="allowReuploads" class="flex items-center mt-3">
                <label for="reupload-existing-asset" class="help-block grow flex items-center cursor-pointer font-normal">
                    <span class="basis-6 flex items-center">
                        <input type="checkbox" name="reupload" id="reupload-existing-asset" class="mr-2" v-model="value.reupload">
                    </span>
                    <span>{{ __('statamic-mux::messages.mirror_fieldtype.reupload_on_save') }}</span>
                </label>
            </div>
        </div>
    </div>
</template>

<script>
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';
import { Button, Description, Icon } from '@statamic/cms/ui';
import DescriptionWithIcon from '../DescriptionWithIcon.vue';

export default {
    mixins: [Fieldtype],
    components: {
        Button,
        Description,
        DescriptionWithIcon,
        Icon
    },
    computed: {
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
            return this.value.id;
        },
        details() {
            if (! this.isUploaded || ! this.showDetails) return [];

            const muxId = this.value.id;
            const playbackIds = Object.entries(this.value.playback_ids || {})
                .sort(([a], [b]) => a.localeCompare(b));
            const playbackId = this.value.playback_id;
            const playbackPolicy = this.value.playback_policy;
            if (! playbackIds.length && playbackId && playbackPolicy) {
                playbackIds.push([playbackPolicy, playbackId]);
            }

            const rows = [];

            rows.push({ key: 'id', label: 'Mux ID', value: muxId });

            for (const [policy, id] of playbackIds) {
                const icon = playbackIds.length > 1
                    ? (policy === 'signed' ? 'security-lock' : 'eye')
                    : null;
                rows.push({ key: id, label: 'Playback ID', value: id, icon });
            }

            return rows.filter(({ value }) => value);
        }
    }
};
</script>

<style>
.mux-table-wrapper {
    overflow: hidden;
    border-radius: .25rem;
    border-width: 1px
}

.mux-table {
    width: 100%;
    border-radius: .25rem;
    text-align: left;
    font-size: 13px;
    background-color: rgb(250 252 255 / var(--tw-bg-opacity));
    color: rgb(115 128 140 / var(--tw-text-opacity));
}

.mux-table tr:not(:last-child) th,.mux-table tr:not(:last-child) td {
    border-bottom-width: 1px
}

.mux-table th,
.mux-table td {
    vertical-align: top;
}

.mux-table th {
    border-right-width: 1px;
    padding: .5rem;
    white-space: nowrap;
    font-weight: normal;
}

.mux-table td {
    margin: 0;
    padding: .5rem;
}

.mux-table tr:first-child th {
    border-top-left-radius: .25rem
}

.mux-table tr:first-child td,.mux-table tr:first-child .input-text-minimal {
    border-top-right-radius: .25rem
}

.mux-table tr:last-child th {
    border-bottom-left-radius: .25rem
}

.mux-table tr:last-child td:last-child,.mux-table tr:last-child .input-minimal {
    border-bottom-right-radius: .25rem
}

.mux-table:focus-within {
    outline: 0;
    box-shadow: none
}

.mux-table td:focus-within {
    --tw-bg-opacity: 1;
    background-color: rgb(245 248 252 / var(--tw-bg-opacity))
}

.mux-table th {
    /* color: rgb(196 204 212 / 1); */
}

.mux-table tr td {
    /* background-color: rgb(245 248 252 / 1); */
}
</style>
