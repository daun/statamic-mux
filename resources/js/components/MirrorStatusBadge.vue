<template>
    <span class="flex items-center gap-2">
        <ui-badge v-if="value" pill :color="color" size="sm" v-tooltip="tooltip">
            {{ label }}
        </ui-badge>
        <ui-icon v-if="isProxy" class="size-3! text-green-700 dark:text-green-300" name="page-ghost" v-tooltip="__('Local asset is a short placeholder clip for the original video')"></ui-icon>
    </span>
</template>

<script>
export default {
    props: {
        value: { type: String, default: null },
        isProxy: { type: Boolean, default: false },
    },

    data() {
        return {
            states: {
                uploaded: {
                    color: 'green',
                    label: __('Uploaded'),
                    tooltip: __('Mirrored to Mux'),
                },
                not_uploaded: {
                    color: 'amber',
                    label: __('Not uploaded'),
                    tooltip: __('Not been mirrored to Mux yet'),
                },
                missing: {
                    color: 'red',
                    label: __('Missing'),
                    tooltip: __('References a Mux asset that does not exist'),
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
