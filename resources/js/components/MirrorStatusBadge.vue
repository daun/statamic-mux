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
                uploaded: 'green',
                not_uploaded: 'amber',
                missing: 'red',
            }[this.value] || 'gray';
        },

        label() {
            return {
                uploaded: __('Uploaded'),
                not_uploaded: __('Not uploaded'),
                missing: __('Missing'),
            }[this.value] || this.value;
        },

        tooltip() {
            return {
                uploaded: __('Asset has been mirrored to Mux'),
                not_uploaded: __('Asset has not been mirrored to Mux yet'),
                missing: __('References a Mux asset that does not exist'),
            }[this.value] || null;
        },
    },
};
</script>
