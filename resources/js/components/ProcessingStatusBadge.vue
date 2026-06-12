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
                ready: {
                    color: 'green',
                    label: __('Ready'),
                    tooltip: __('Mux has finished processing this asset'),
                },
                preparing: {
                    color: 'blue',
                    label: __('Preparing'),
                    tooltip: __('Mux is processing this asset'),
                },
                errored: {
                    color: 'red',
                    label: __('Errored'),
                    tooltip: __('Mux could not process this asset'),
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
