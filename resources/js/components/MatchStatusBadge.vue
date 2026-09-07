<template>
    <ui-badge v-if="value" pill :color="color" size="sm" v-tooltip="tooltip">
        {{ label }}
    </ui-badge>
</template>

<script>
export default {
    props: {
        value: { type: String, default: null },
    },

    data() {
        return {
            states: {
                linked: {
                    color: 'green',
                    label: __('Linked'),
                    tooltip: __('A local asset uses this encoding'),
                },
                'shared-reference': {
                    color: 'amber',
                    label: __('Shared'),
                    tooltip: __('Referenced by more than one local asset'),
                },
                'non-ready-linked': {
                    color: 'amber',
                    label: __('Not ready'),
                    tooltip: __('The linked encoding never finished processing'),
                },
                unlinked: {
                    color: 'blue',
                    label: __('Can be re-linked'),
                    tooltip: __('The local asset exists but is not linked'),
                },
                'proxy-source': {
                    color: 'blue',
                    label: __('Placeholder source'),
                    tooltip: __('The local asset is the placeholder clip for this encoding'),
                },
                superseded: {
                    color: 'gray',
                    label: __('Superseded'),
                    tooltip: __('Replaced by a newer upload'),
                },
                'missing-source': {
                    color: 'gray',
                    label: __('Source deleted'),
                    tooltip: __('The local asset no longer exists'),
                },
                'unmanaged-source': {
                    color: 'amber',
                    label: __('No Mux field'),
                    tooltip: __('The local asset exists but its blueprint has no Mux field'),
                },
                preparing: {
                    color: 'blue',
                    label: __('Preparing'),
                    tooltip: __('Mux is still processing this asset'),
                },
                errored: {
                    color: 'red',
                    label: __('Errored'),
                    tooltip: __('Encoding failed on Mux'),
                },
                'unknown-status': {
                    color: 'gray',
                    label: __('Unknown'),
                    tooltip: __('Mux reported an unrecognized processing status'),
                },
                'media-mismatch': {
                    color: 'red',
                    label: __('Mismatch'),
                    tooltip: __("The encoding doesn't match the local asset's duration or aspect ratio"),
                },
                'attribution-conflict': {
                    color: 'red',
                    label: __('Conflict'),
                    tooltip: __('Passthrough and metadata disagree about the source'),
                },
                unattributable: {
                    color: 'amber',
                    label: __('Unattributable'),
                    tooltip: __('Created by this addon but no source can be resolved'),
                },
                foreign: {
                    color: 'gray',
                    label: __('Not from Statamic'),
                    tooltip: __('This asset was not created by this addon'),
                },
                'proxy-in-flight': {
                    color: 'blue',
                    label: __('Placeholder'),
                    tooltip: __('Temporary placeholder clip for an original asset'),
                },
                'expired-proxy': {
                    color: 'gray',
                    label: __('Expired placeholder'),
                    tooltip: __('Placeholder clip past its grace period'),
                },
                'orphaned-proxy': {
                    color: 'gray',
                    label: __('Stray placeholder'),
                    tooltip: __('Placeholder whose original no longer exists'),
                },
            },
        };
    },

    computed: {
        state() {
            return this.states[this.value] || { color: 'gray', label: this.value, tooltip: null };
        },
        color() {
            return this.state.color;
        },
        label() {
            return this.state.label;
        },
        tooltip() {
            return this.state.tooltip;
        },
    },
};
</script>
