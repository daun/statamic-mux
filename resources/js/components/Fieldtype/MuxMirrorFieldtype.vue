<template>
    <div>
        <div v-if="!isAsset || !isVideo" class="help-block mb-0 flex items-center">
            <svg-icon name="light/hidden" class="h-4 w-4 mr-2" />
            <span>
                {{ __('statamic-mux::messages.mirror_fieldtype.not_mirrored') }}:
                <template v-if="!isVideo">
                    {{ __('statamic-mux::messages.mirror_fieldtype.no_video') }}
                </template>
                <template v-else>
                    {{ __('statamic-mux::messages.mirror_fieldtype.no_asset') }}
                </template>
            </span>
        </div>
        <div v-else-if="!hasData">
            <div class="help-block mb-0 flex items-center">
                <svg-icon name="light/hyperlink-broken" class="h-4 w-4 mr-2" />
                <span>
                    {{ __('statamic-mux::messages.mirror_fieldtype.not_uploaded') }}
                </span>
            </div>
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
            <div class="help-block mb-0 flex items-center">
                <svg-icon name="light/hyperlink" class="h-4 w-4 mr-2" />
                <span :title="this.value.id">
                    {{ __('statamic-mux::messages.mirror_fieldtype.uploaded') }}
                </span>
            </div>
            <div v-if="showDetails" class="mux-table-wrapper mt-3">
                <table class="mux-table">
                    <tbody>
                        <tr v-for="row in rows" :key="row.key">
                            <th>
                                {{ row.label || row.key }}
                            </th>
                            <td>
                                {{ row.value }}
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
export default {
    mixins: [Fieldtype],
    data() {
        return {
            details: false,
            labels: {
              'id': 'Mux ID',
              'playback_id': 'Playback ID',
              'playback_policy': 'Playback Policy',
            }
        };
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
        hasData() {
            return this.rows.length;
        },
        hasMuxData() {
            return arr_get(this.field, 'config.has_mux_data', false);
        },
        rows() {
            const rows = ['id', 'playback_id', 'playback_policy']
            return Object.entries(this.value || {})
                .filter(([key]) => rows.includes(key))
                .sort(([a], [b]) => rows.indexOf(a) - rows.indexOf(b))
                .map(([key, value]) => {
                    const label = this.labels[key] || key;
                    return { key, value, label };
                });
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
