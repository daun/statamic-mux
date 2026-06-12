<template>
    <Badge v-if="value" pill :color="color" class="text-2xs" v-tooltip="tooltip">
        {{ label }}
    </Badge>
</template>

<script>
import { Badge } from '@statamic/cms/ui';

export default {
    components: { Badge },

    props: {
        value: { type: String, default: null },
    },

    computed: {
        color() {
            return {
                ready: 'green',
                preparing: 'blue',
                errored: 'red',
            }[this.value] || 'gray';
        },

        label() {
            return {
                ready: __('Ready'),
                preparing: __('Preparing'),
                errored: __('Errored'),
            }[this.value] || this.value;
        },

        tooltip() {
            return {
                ready: __('Mux has finished processing this asset'),
                preparing: __('Mux is processing this asset'),
                errored: __('Mux could not process this asset'),
            }[this.value] || null;
        },
    },
};
</script>
