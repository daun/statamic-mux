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
                mirrored: {
                    color: 'green',
                    label: __('Mirrored'),
                    tooltip: __('Linked to a local asset'),
                },
                orphaned: {
                    color: 'amber',
                    label: __('Orphaned'),
                    tooltip: __('Not linked to a local asset'),
                },
                duplicated: {
                    color: 'red',
                    label: __('Duplicated'),
                    tooltip: __('Referenced by multiple local assets'),
                },
                proxy: {
                    color: 'blue',
                    label: __('Placeholder'),
                    tooltip: __('Temporary placeholder clip for an original asset'),
                },
                fallback: {
                    color: 'gray',
                    label: this.value,
                    tooltip: null,
                },
            },
        };
    },

    computed: {
        state() {
            return this.states[this.value] || { ...this.states.fallback, label: this.value };
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
